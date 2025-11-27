import {
    useBlockProps,
    InspectorControls,
} from '@wordpress/block-editor';
import {
    PanelBody,
    SelectControl,
    TextControl,
    Notice,
    Spinner,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import ServerSideRender from '@wordpress/server-side-render';

const FAQ_POST_TYPE = 'rrze_faq';
const FAQ_TAXONOMY = 'rrze_faq_category';

const DISPLAY_OPTIONS = [
    { label: 'Show question and answer', value: 1 },
    { label: 'Show question and answer opened', value: 2 },
    { label: 'Hide question', value: 3 },
];

export default function Edit({ attributes, setAttributes }) {
    const { id, catID, start, end, display } = attributes;

    // Fetch FAQs and categories via REST API.
    const {
        faqs,
        categories,
        isLoadingFaqs,
        isLoadingCategories,
    } = useSelect((select) => {
        const core = select('core');

        const faqQuery = {
            per_page: -1,
            orderby: 'title',
            order: 'asc',
            status: 'publish',
        };

        const categoryQuery = {
            per_page: -1,
            hide_empty: false,
            orderby: 'name',
            order: 'asc',
        };

        const faqRecords = core.getEntityRecords('postType', FAQ_POST_TYPE, faqQuery);
        const categoryRecords = core.getEntityRecords('taxonomy', FAQ_TAXONOMY, categoryQuery);

        const isResolvingFaqs = core.isResolving('getEntityRecords', [
            'postType',
            FAQ_POST_TYPE,
            faqQuery,
        ]);

        const isResolvingCategories = core.isResolving('getEntityRecords', [
            'taxonomy',
            FAQ_TAXONOMY,
            categoryQuery,
        ]);

        return {
            faqs: faqRecords || [],
            categories: categoryRecords || [],
            isLoadingFaqs: isResolvingFaqs,
            isLoadingCategories: isResolvingCategories,
        };
    }, []);

    const blockProps = useBlockProps();

    // Build select options for FAQs.
    const faqOptions = [
        { label: '— Select FAQ —', value: 0 },
        ...faqs.map((faq) => ({
            label: faq.title?.rendered || `#${faq.id}`,
            value: faq.id,
        })),
    ];

    // Build select options for categories.
    const categoryOptions = [
        { label: '— Select category —', value: 0 },
        ...categories.map((term) => ({
            label: term.name,
            value: term.id,
        })),
    ];

    // Handle FAQ selection.
    const onChangeFAQ = (value) => {
        const intValue = parseInt(value, 10) || 0;
        setAttributes({
            id: intValue,
        });
    };

    // Handle category selection.
    const onChangeCategory = (value) => {
        const intValue = parseInt(value, 10) || 0;
        setAttributes({
            catID: intValue,
        });
    };

    // Handle date change.
    const onChangeStart = (value) => {
        setAttributes({ start: value });
    };

    const onChangeEnd = (value) => {
        setAttributes({ end: value });
    };

    // Handle display option change.
    const onChangeDisplay = (value) => {
        const intValue = parseInt(value, 10) || 1;
        setAttributes({ display: intValue });
    };

    return (
        <>
            <InspectorControls>
                <PanelBody title="FAQ selection" initialOpen={true}>
                    {isLoadingFaqs && <Spinner />}

                    {!isLoadingFaqs && !faqs.length && (
                        <Notice status="warning" isDismissible={false}>
                            No FAQs found (post type &quot;rrze_faq&quot; must be public and show_in_rest).
                        </Notice>
                    )}

                    <SelectControl
                        label="Choose a FAQ"
                        value={id}
                        options={faqOptions}
                        onChange={onChangeFAQ}
                        help="Select a specific FAQ post."
                    />

                    <SelectControl
                        label="Or choose a category to display a random FAQ"
                        value={catID}
                        options={categoryOptions}
                        onChange={onChangeCategory}
                        disabled={!categories.length && !isLoadingCategories}
                        help="If a category is selected and no specific FAQ is set, a random FAQ from this category will be used."
                    />
                </PanelBody>

                <PanelBody title="Date range (optional)" initialOpen={false}>
                    <TextControl
                        label="Start date"
                        type="date"
                        value={start}
                        onChange={onChangeStart}
                    />
                    <TextControl
                        label="End date"
                        type="date"
                        value={end}
                        onChange={onChangeEnd}
                    />
                </PanelBody>

                <PanelBody title="Display options" initialOpen={false}>
                    <SelectControl
                        label="Display"
                        value={display || 1}
                        options={DISPLAY_OPTIONS}
                        onChange={onChangeDisplay}
                    />
                </PanelBody>
            </InspectorControls>

            <div {...blockProps}>
                {/* Server-side preview of the FAQ output */}
                <ServerSideRender
                    block="rrze-answers/faqwidget"
                    attributes={attributes}
                />
            </div>
        </>
    );
};
