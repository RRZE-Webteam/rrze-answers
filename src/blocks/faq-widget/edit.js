import {
    useBlockProps,
    InspectorControls,
} from '@wordpress/block-editor';
import {
    PanelBody,
    SelectControl,
    Notice,
    Spinner,
    CheckboxControl,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import ServerSideRender from '@wordpress/server-side-render';
import { __ } from '@wordpress/i18n';

const FAQ_POST_TYPE = 'rrze_faq';
const FAQ_TAXONOMY = 'rrze_faq_category';

export default function Edit( { attributes, setAttributes } ) {
    const { id, catID, hide_title } = attributes;

    // Fetch FAQs and categories via REST API.
    const {
        faqs,
        categories,
        isLoadingFaqs,
        isLoadingCategories,
    } = useSelect( ( select ) => {
        const core = select( 'core' );

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

        const faqRecords = core.getEntityRecords( 'postType', FAQ_POST_TYPE, faqQuery );
        const categoryRecords = core.getEntityRecords( 'taxonomy', FAQ_TAXONOMY, categoryQuery );

        const isResolvingFaqs = core.isResolving( 'getEntityRecords', [
            'postType',
            FAQ_POST_TYPE,
            faqQuery,
        ] );

        const isResolvingCategories = core.isResolving( 'getEntityRecords', [
            'taxonomy',
            FAQ_TAXONOMY,
            categoryQuery,
        ] );

        return {
            faqs: faqRecords || [],
            categories: categoryRecords || [],
            isLoadingFaqs: isResolvingFaqs,
            isLoadingCategories: isResolvingCategories,
        };
    }, [] );

    const blockProps = useBlockProps();

    // Build select options for FAQs.
    const faqOptions = [
        { label: __( '— Select FAQ —', 'rrze-answers' ), value: 0 },
        ...faqs.map( ( faq ) => ( {
            label: faq.title?.rendered || `#${ faq.id }`,
            value: faq.id,
        } ) ),
    ];

    // Build select options for categories.
    const categoryOptions = [
        { label: __( '— Select category —', 'rrze-answers' ), value: 0 },
        ...categories.map( ( term ) => ( {
            label: term.name,
            value: term.id,
        } ) ),
    ];

    // Handle FAQ selection.
    const onChangeFAQ = ( value ) => {
        const intValue = parseInt( value, 10 ) || 0;
        setAttributes( {
            id: intValue,
        } );
    };

    // Handle category selection.
    const onChangeCategory = ( value ) => {
        const intValue = parseInt( value, 10 ) || 0;
        setAttributes( {
            catID: intValue,
        } );
    };

    // Handle hide_title checkbox.
    const onChangeHideTitle = ( checked ) => {
        setAttributes( { hide_title: checked ? 1 : 0 } );
    };

    return (
        <>
            <InspectorControls>
                <PanelBody
                    title={ __( 'FAQ selection', 'rrze-answers' ) }
                    initialOpen={ true }
                >
                    { isLoadingFaqs && <Spinner /> }

                    { ! isLoadingFaqs && ! faqs.length && (
                        <Notice status="warning" isDismissible={ false }>
                            { __(
                                'No FAQs found (post type "rrze_faq" must be public and show_in_rest).',
                                'rrze-answers'
                            ) }
                        </Notice>
                    ) }

                    <SelectControl
                        label={ __( 'Choose a FAQ', 'rrze-answers' ) }
                        value={ id }
                        options={ faqOptions }
                        onChange={ onChangeFAQ }
                    />

                    <SelectControl
                        label={ __(
                            'Or',
                            'rrze-answers'
                        ) }
                        value={ catID }
                        options={ categoryOptions }
                        onChange={ onChangeCategory }
                        disabled={ ! categories.length && ! isLoadingCategories }
                    />
                </PanelBody>

                <PanelBody
                    title={ __( 'Display options', 'rrze-answers' ) }
                    initialOpen={ false }
                >
                    <CheckboxControl
                        label={ __( 'Hide question title', 'rrze-answers' ) }
                        checked={ !! hide_title }
                        onChange={ onChangeHideTitle }
                        help={ __(
                            'If enabled, the FAQ title will be hidden.',
                            'rrze-answers'
                        ) }
                    />
                </PanelBody>
            </InspectorControls>

            <div { ...blockProps }>
                {/* Server-side preview of the FAQ output */}
                <ServerSideRender
                    block="rrze-answers/faq-widget"
                    attributes={ attributes }
                />
            </div>
        </>
    );
}
