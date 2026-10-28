<?php

declare(strict_types=1);

namespace RRZE\Answers\Common\Sync;

defined('ABSPATH') || exit;

/**
 * Resolves source-site taxonomy values without using destination term IDs.
 *
 * Supported source generations, in order of preference:
 * 1. The collision-free term-name fields exposed by current plugin versions.
 * 2. WordPress core taxonomy fields accompanied by `_embedded` REST terms.
 * 3. Native source term IDs resolved through the source taxonomy endpoint.
 *
 * Names returned directly in the native taxonomy field remain supported for
 * older plugin versions that registered a custom field under that name.
 */
final class RemoteTaxonomyResolver
{
    /** @var array<string, array<int, string>> */
    private array $termNamesByTaxonomyAndId = [];

    /**
     * Reset the snapshot-local cache before synchronizing another collection.
     */
    public function reset(): void
    {
        $this->termNamesByTaxonomyAndId = [];
    }

    /**
     * Resolve one entry's taxonomy values to names.
     *
     * @param array<string, mixed> $remoteEntry
     * @return string[]|\WP_Error
     */
    public function resolveNames(
        array $remoteEntry,
        string $sourceUrl,
        string $taxonomy,
        string $termNamesField
    ) {
        if (array_key_exists($termNamesField, $remoteEntry)) {
            return $this->validateExplicitNames($remoteEntry[$termNamesField]);
        }

        $nativeTerms = $remoteEntry[$taxonomy] ?? [];
        if (!is_array($nativeTerms)) {
            return new \WP_Error(
                'remote_invalid_taxonomy',
                __('The source site returned invalid taxonomy data. Existing synchronized entries were preserved.', 'rrze-answers')
            );
        }

        $this->cacheEmbeddedTerms($remoteEntry, $taxonomy);

        $names = [];
        $termIds = [];

        foreach ($nativeTerms as $nativeTerm) {
            if (is_string($nativeTerm) && !is_numeric($nativeTerm)) {
                if ($nativeTerm !== '') {
                    $names[] = $nativeTerm;
                }
                continue;
            }

            if (is_numeric($nativeTerm) && (int) $nativeTerm > 0) {
                $termIds[] = (int) $nativeTerm;
                continue;
            }

            return new \WP_Error(
                'remote_invalid_taxonomy',
                __('The source site returned invalid taxonomy data. Existing synchronized entries were preserved.', 'rrze-answers')
            );
        }

        $missingTermIds = array_values(
            array_filter(
                array_unique($termIds),
                fn (int $termId): bool => !isset(
                    $this->termNamesByTaxonomyAndId[$taxonomy][$termId]
                )
            )
        );

        if ($missingTermIds !== []) {
            $resolutionError = $this->fetchMissingTermNames(
                $sourceUrl,
                $taxonomy,
                $missingTermIds
            );
            if ($resolutionError !== null) {
                return $resolutionError;
            }
        }

        foreach ($termIds as $termId) {
            if (!isset($this->termNamesByTaxonomyAndId[$taxonomy][$termId])) {
                return new \WP_Error(
                    'remote_incomplete_taxonomy_response',
                    __('The source site did not return all requested taxonomy terms. Existing synchronized entries were preserved.', 'rrze-answers')
                );
            }

            $names[] = $this->termNamesByTaxonomyAndId[$taxonomy][$termId];
        }

        return array_values(array_unique($names));
    }

    /**
     * Validate the collision-free name field from a current source.
     *
     * @param mixed $termNames
     * @return string[]|\WP_Error
     */
    private function validateExplicitNames($termNames)
    {
        if (!is_array($termNames) || !array_is_list($termNames)) {
            return new \WP_Error(
                'remote_invalid_taxonomy_names',
                __('The source site returned invalid taxonomy names. Existing synchronized entries were preserved.', 'rrze-answers')
            );
        }

        $validatedNames = [];

        foreach ($termNames as $termName) {
            if (!is_string($termName) || $termName === '') {
                return new \WP_Error(
                    'remote_invalid_taxonomy_names',
                    __('The source site returned invalid taxonomy names. Existing synchronized entries were preserved.', 'rrze-answers')
                );
            }

            $validatedNames[] = $termName;
        }

        return array_values(array_unique($validatedNames));
    }

    /**
     * Cache matching terms included by WordPress's `_embed=wp:term` support.
     *
     * @param array<string, mixed> $remoteEntry
     */
    private function cacheEmbeddedTerms(array $remoteEntry, string $taxonomy): void
    {
        $embeddedGroups = $remoteEntry['_embedded']['wp:term'] ?? [];
        if (!is_array($embeddedGroups)) {
            return;
        }

        foreach ($embeddedGroups as $embeddedGroup) {
            if (!is_array($embeddedGroup)) {
                continue;
            }

            if ($this->isTermRecord($embeddedGroup)) {
                $this->cacheTermRecord($embeddedGroup, $taxonomy);
                continue;
            }

            foreach ($embeddedGroup as $termRecord) {
                if (is_array($termRecord)) {
                    $this->cacheTermRecord($termRecord, $taxonomy);
                }
            }
        }
    }

    /**
     * Determine whether an array represents one REST term rather than a group.
     *
     * @param array<mixed> $value
     */
    private function isTermRecord(array $value): bool
    {
        return isset($value['id'], $value['name'], $value['taxonomy']);
    }

    /**
     * Cache a valid term record belonging to the requested taxonomy.
     *
     * @param array<mixed> $termRecord
     */
    private function cacheTermRecord(array $termRecord, string $taxonomy): void
    {
        if (
            ($termRecord['taxonomy'] ?? null) !== $taxonomy
            || !isset($termRecord['id'], $termRecord['name'])
            || (int) $termRecord['id'] <= 0
            || !is_string($termRecord['name'])
            || $termRecord['name'] === ''
        ) {
            return;
        }

        $this->termNamesByTaxonomyAndId[$taxonomy][(int) $termRecord['id']]
            = $termRecord['name'];
    }

    /**
     * Fetch source terms that were not present in the embedded response.
     *
     * @param int[] $missingTermIds
     */
    private function fetchMissingTermNames(
        string $sourceUrl,
        string $taxonomy,
        array $missingTermIds
    ): ?\WP_Error {
        foreach (array_chunk($missingTermIds, 100) as $termIdChunk) {
            $response = $this->request(
                trailingslashit($sourceUrl) . ENDPOINT . $taxonomy
                    . '?include=' . implode(',', $termIdChunk) . '&per_page=100'
            );

            if (is_wp_error($response)) {
                return $response;
            }

            $statusCode = (int) wp_remote_retrieve_response_code($response);
            if ($statusCode === 403) {
                return new \WP_Error(
                    'remote_forbidden',
                    __('Import not allowed by source site.', 'rrze-answers')
                );
            }

            if ($statusCode !== 200) {
                return new \WP_Error(
                    'remote_taxonomy_http_error',
                    sprintf(
                        /* translators: %d: HTTP response status code. */
                        __('The source taxonomy endpoint returned HTTP status %d. Existing synchronized entries were preserved.', 'rrze-answers'),
                        $statusCode
                    )
                );
            }

            $termRecords = json_decode(wp_remote_retrieve_body($response), true);
            if (
                json_last_error() !== JSON_ERROR_NONE
                || !is_array($termRecords)
                || !array_is_list($termRecords)
            ) {
                return new \WP_Error(
                    'remote_invalid_taxonomy_response',
                    __('The source site returned an invalid taxonomy response. Existing synchronized entries were preserved.', 'rrze-answers')
                );
            }

            foreach ($termRecords as $termRecord) {
                if (is_array($termRecord)) {
                    $this->cacheTermRecord($termRecord, $taxonomy);
                }
            }
        }

        return null;
    }

    /**
     * Request uncached source taxonomy data through WordPress SSRF protection.
     *
     * @return array<string, mixed>|\WP_Error
     */
    private function request(string $url)
    {
        try {
            return wp_safe_remote_get(
                $url,
                [
                    'sslverify' => !(defined('WP_DEBUG') && WP_DEBUG),
                    'timeout' => 5,
                ]
            );
        } catch (\Throwable $exception) {
            return new \WP_Error('remoteGet_error', __('Error in remoteGet().', 'rrze-answers'));
        }
    }
}
