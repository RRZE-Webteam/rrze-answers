<?php

namespace RRZE\Answers;

use function RRZE\Answers\plugin;

use RRZE\Answers\Defaults;

use RRZE\Answers\Common\{
    HtmlSanitizer,
    Content\PlaceholderRenderer,
    TabsRenderer,
    Tools,
    API\RESTAPI,
    API\SyncAPI,
    AdminInterfaces\AdminUI_QA,
    AdminInterfaces\AdminUI_Synonym,
    AdminInterfaces\AdminUI_Placeholder,
    Settings\Settings,
    CPT\CPTFAQ,
    CPT\CPTGlossary,
    CPT\CPTSynonym,
    CPT\CPTPlaceholder,
    Sync\Sync,
    Sync\SynchronizedSourceRemovalService,
    Shortcode\ShortcodeFAQ,
    Shortcode\ShortcodeGlossary,
    Shortcode\ShortcodeSynonym,
    Shortcode\ShortcodePlaceholder
};

defined('ABSPATH') || exit;

/**
 * Composes the plugin's WordPress hooks and top-level feature objects.
 */
class Main
{
    private const SETTINGS_OPTION = 'rrze-answers';

    private const SETTINGS_NONCE_ACTION = 'rrze-answers_settings_save_rrze-answers';

    private const TEMPORARY_OPTION_FIELDS = [
        'new_name',
        'new_url',
        'faqsync_shortname',
        'faqsync_url',
        'faqsync_categories',
        'faqsync_donotsync',
        'faqsync_hr',
    ];

    /** @var Defaults */
    public $defaults;

    /** @var RESTAPI */
    public $restapi;

    /** @var Settings */
    public $settings;

    private ?Sync $sync = null;

    /**
     * Register hooks that must exist before WordPress initialization.
     */
    public function __construct()
    {
        // Construct translated CPT definitions on init. Their registration
        // callbacks are added at priority 0 and therefore still run during the
        // same init cycle, after the textdomain has loaded at priority -2.
        add_action('init', [$this, 'cpt'], -1);
        add_action('init', [$this, 'onInit']);
        add_filter('wp_kses_allowed_html', [HtmlSanitizer::class, 'allowLegacyPlaceholder'], 10, 2);
        add_filter('the_content', [$this, 'renderInlinePlaceholders'], 9);
    }

    /**
     * Compose features whose labels and settings require initialized WordPress.
     */
    public function onInit(): void
    {
        $this->defaults = new Defaults();
        $this->settings();
        $this->restapi = new RESTAPI();

        new AdminUI_QA('rrze_faq');
        new AdminUI_QA('rrze_glossary');
        new AdminUI_Synonym();
        new AdminUI_Placeholder();

        $this->sync = new Sync();

        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueueBlockEditorStyles']);

        add_filter('pre_update_option_' . self::SETTINGS_OPTION, [$this, 'switchTask'], 10, 1);
        add_action('update_option_' . self::SETTINGS_OPTION, [$this, 'maybeSync'], 10, 2);

        $this->shortcode();
    }


    /**
     * Run synchronization after import settings actually changed.
     *
     * @param mixed $oldOptions
     * @param mixed $newOptions
     */
    public function maybeSync($oldOptions, $newOptions): void
    {
        if (
            $oldOptions == $newOptions
            || $this->getCurrentSettingsTab() !== 'import'
            || !$this->sync instanceof Sync
        ) {
            return;
        }

        $newOptions = is_array($newOptions) ? $newOptions : [];
        $frequency = !empty($newOptions['frequency'])
            ? (string) $newOptions['frequency']
            : '';
        $mode = $frequency !== '' ? 'automatic' : 'manual';

        $this->sync->doSync($mode);
        $this->sync->setCronjob($frequency);
    }

    /**
     * Apply settings-page side effects before the option is persisted.
     *
     * @param mixed $submittedOptions
     * @return array<string, mixed>
     */
    public function switchTask($submittedOptions): array
    {
        $options = is_array($submittedOptions) ? $submittedOptions : [];
        $storedOptions = get_option(self::SETTINGS_OPTION);

        if (is_array($storedOptions)) {
            $options = array_merge($storedOptions, $options);
        }

        $syncAPI = new SyncAPI();
        $domains = $syncAPI->getDomains();

        $settingsTab = $this->getCurrentSettingsTab();

        if ($settingsTab === 'domains') {
            $this->updateDomainSettings($options, $domains, $syncAPI);
        } elseif ($settingsTab === 'del') {
            Tools::deleteLogfile();
        }

        if ($domains === []) {
            unset($options['registeredDomains']);
        } else {
            $options['registeredDomains'] = $domains;
        }

        foreach (self::TEMPORARY_OPTION_FIELDS as $fieldName) {
            unset($options[$fieldName]);
        }

        return $options;
    }

    /**
     * Add a source or remove the selected registered sources.
     *
     * @param array<string, mixed>  $options
     * @param array<string, string> $domains
     */
    private function updateDomainSettings(
        array &$options,
        array &$domains,
        SyncAPI $syncAPI
    ): void {
        $newUrl = isset($options['new_url']) ? (string) $options['new_url'] : '';

        if ($newUrl !== '' && $newUrl !== 'https://') {
            $this->registerDomain($newUrl, $domains, $syncAPI);
            return;
        }

        $this->removeRequestedDomains($options, $domains);
    }

    /**
     * Validate and add one synchronization source.
     *
     * @param array<string, string> $domains
     */
    private function registerDomain(
        string $submittedUrl,
        array &$domains,
        SyncAPI $syncAPI
    ): void {
        $identifier = (string) Tools::getIdentifier($submittedUrl);
        $url = 'https://' . (string) Tools::getHost($submittedUrl);
        $domainCheck = $syncAPI->checkDomain($identifier, $url, $domains);

        if ($domainCheck['status']) {
            $domains[$identifier] = $url;
            return;
        }

        add_settings_error(
            'new_url',
            'domains_new_error',
            $domainCheck['msg'],
            'error'
        );
    }

    /**
     * Remove selected sources only after their posts were safely trashed.
     *
     * @param array<string, mixed>  $options
     * @param array<string, string> $domains
     */
    private function removeRequestedDomains(array &$options, array &$domains): void
    {
        $sourceIdentifiers = $this->getRequestedSourceIdentifiers();
        if ($sourceIdentifiers === []) {
            return;
        }

        if (!$this->isAuthorizedSourceRemovalRequest()) {
            add_settings_error(
                self::SETTINGS_OPTION,
                'source_removal_error',
                __('The synchronization sources could not be removed because the settings request was not authorized.', 'rrze-answers'),
                'error'
            );
            return;
        }

        $removalResult = (new SynchronizedSourceRemovalService())->remove(
            $sourceIdentifiers,
            $domains
        );

        if (is_wp_error($removalResult)) {
            add_settings_error(
                self::SETTINGS_OPTION,
                'source_removal_error',
                $removalResult->get_error_message(),
                'error'
            );
            return;
        }

        foreach ($removalResult['removedSourceIdentifiers'] as $identifier) {
            unset($domains[$identifier]);
            unset($options['faq_categories_' . $identifier]);
            unset($options['glossary_categories_' . $identifier]);
        }
    }

    /**
     * Return the sanitized settings tab selected by the current request.
     */
    private function getCurrentSettingsTab(): string
    {
        $submittedTab = $_GET['tab'] ?? null;

        if (!is_scalar($submittedTab)) {
            return '';
        }

        return sanitize_key((string) wp_unslash($submittedTab));
    }

    /**
     * Read selected source identifiers from the verified settings request.
     *
     * Non-scalar values are ignored and every scalar identifier is sanitized.
     * The removal service performs the authoritative registered-source check
     * before any synchronized post is changed.
     *
     * @return string[]
     */
    private function getRequestedSourceIdentifiers(): array
    {
        $sourceIdentifiers = [];

        foreach ($_POST as $fieldName => $submittedValue) {
            if (
                !str_starts_with((string) $fieldName, 'del_domain_')
                || !is_scalar($submittedValue)
            ) {
                continue;
            }

            $sourceIdentifier = sanitize_text_field(
                (string) wp_unslash($submittedValue)
            );

            if ($sourceIdentifier !== '') {
                $sourceIdentifiers[] = $sourceIdentifier;
            }
        }

        return array_values(array_unique($sourceIdentifiers));
    }

    /**
     * Verify the capability and nonce before interpreting deletion checkboxes.
     */
    private function isAuthorizedSourceRemovalRequest(): bool
    {
        if (!current_user_can('manage_options')) {
            return false;
        }

        $submittedNonce = $_POST['rrze-answers_settings_save'] ?? null;
        if (!is_scalar($submittedNonce)) {
            return false;
        }

        return wp_verify_nonce(
            sanitize_text_field((string) wp_unslash($submittedNonce)),
            self::SETTINGS_NONCE_ACTION
        ) !== false;
    }


    /**
     * Replace legacy inline markers using the same renderer as placeholder blocks.
     *
     * @param mixed $content
     * @return mixed
     */
    public function renderInlinePlaceholders($content)
    {
        if (!is_string($content) || stripos($content, '<placeholder') === false) {
            return $content;
        }

        if (is_admin() && !wp_doing_ajax()) {
            return $content;
        }

        return PlaceholderRenderer::replaceInline($content);
    }

    /**
     * Construct custom post types early enough to register during `init`.
     */
    public function cpt(): void
    {
        new CPTFAQ();
        new CPTGlossary();
        new CPTSynonym();
        new CPTPlaceholder();
    }

    /**
     * Register all classic shortcode handlers.
     */
    public function shortcode(): void
    {
        new ShortcodeFAQ();
        new ShortcodeGlossary();
        new ShortcodeSynonym();
        new ShortcodePlaceholder();
    }

    /**
     * Build the plugin settings page from Defaults configuration.
     */
    public function settings(): void
    {
        $this->settings = new Settings($this->defaults->get('settings')['page_title']);

        $this->settings->setCapability($this->defaults->get('settings')['capability'])
            ->setOptionName($this->defaults->get('settings')['option_name'])
            ->setMenuTitle($this->defaults->get('settings')['menu_title'])
            ->setMenuPosition(6)
            ->setMenuParentSlug('options-general.php');

        foreach ($this->defaults->get('sections') as $section) {
            $settingsTab = $this->settings->addTab(
                __($section['title'], 'rrze-answers'),
                $section['id']
            );
            $settingsSection = $settingsTab->addSection(
                __($section['title'], 'rrze-answers'),
                $section['id']
            );

            foreach ($this->defaults->get('fields')[$section['id']] as $field) {
                $settingsSection->addOption($field['type'], array_intersect_key(
                    $field,
                    array_flip(['name', 'label', 'description', 'options', 'default', 'sanitize', 'validate', 'synonym'])
                ));
            }
        }

        $this->settings->build();
    }

    /**
     * Register shared front-end assets and enqueue those needed immediately.
     */
    public function enqueueAssets(): void
    {
        if (is_admin()) {
            wp_enqueue_script('rrze-answers-accordion');
            wp_enqueue_script('rrze-answers-search');
        } elseif (is_singular()) {
            $post = get_queried_object();
            if ($post instanceof \WP_Post && str_contains($post->post_content, 'rrze-syn')) {
                wp_enqueue_style('rrze-answers-css');
            }
        }
    }

    public function enqueueBlockEditorStyles(): void
    {
        wp_enqueue_style('rrze-answers-css');

        $settings = 'window.rrzeAnswersBlockSettings = '
            . wp_json_encode([
                'elementsTabsAvailable' => TabsRenderer::isAvailable(),
            ])
            . ';';

        foreach (['faq', 'glossary'] as $blockName) {
            wp_add_inline_script(
                generate_block_asset_handle(
                    'rrze-answers/' . $blockName,
                    'editorScript'
                ),
                $settings,
                'before'
            );
        }
    }

    /**
     * Load admin assets only on Answers post, taxonomy, and settings screens.
     */
    public function enqueueAdminAssets(): void
    {
        $screen = get_current_screen();
        if (!$screen) {
            return;
        }

        $relevantPostTypes = ['rrze_faq', 'rrze_glossary', 'rrze_synonym', 'rrze_placeholder'];
        $relevantTaxonomies = [
            'rrze_faq_category',
            'rrze_faq_tag',
            'rrze_glossary_category',
            'rrze_glossary_tag',
            'rrze_synonym_group',
            'rrze_synonym_tag',
        ];
        $relevantPages = [
            'rrze-answers',
            'rrze-answers_faq',
            'rrze-answers_glossary',
            'rrze-answers_synonym',
            'rrze-answers_placeholder',
        ];
        $isRelevantScreen = in_array($screen->post_type, $relevantPostTypes, true)
            || in_array($screen->taxonomy, $relevantTaxonomies, true)
            || in_array($screen->id, $relevantPages, true);

        if (!$isRelevantScreen) {
            return;
        }

        wp_enqueue_style('rrze-answers-admin-css');
        wp_enqueue_script('rrze-answers-accordion');
        wp_enqueue_script('rrze-answers-search');
    }
}
