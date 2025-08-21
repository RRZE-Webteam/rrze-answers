/**
 * Retrieves the translation of text.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-i18n/
 */
import {__} from '@wordpress/i18n';
import {useState} from '@wordpress/element';
import {useSelect} from '@wordpress/data';
import {InspectorControls, useBlockProps} from '@wordpress/block-editor';
import {PanelBody, SelectControl} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';


export default function Edit({attributes, setAttributes}) {
    const {
        register,
        tag,
        id,
        hstart,
        order,
        sort,
        lang,
        additional_class,
        color,
        load_open,
        expand_all_link,
        hide_title,
        hide_accordion,
        synonymstyle,
        synonym
    } = attributes;
    const blockProps = useBlockProps();
    const [registerstate, setSelectedCategories] = useState(['']);
    const [tagstate, setSelectedTags] = useState(['']);
    const [idstate, setSelectedIDs] = useState(['']);

    const categories = useSelect((select) => {
        return select('core').getEntityRecords('taxonomy', 'synonym_category');
    }, []);

    const registeroptions = [
        {
            label: __('all', 'rrze-synonym'),
            value: ''
        }
    ];

    if (!!categories) {
        Object.values(categories).forEach(register => {
            registeroptions.push({
                label: register.name,
                value: register.slug,
            });
        });
    }

    const tags = useSelect((select) => {
        return select('core').getEntityRecords('taxonomy', 'synonym_tag');
    }, []);

    const tagoptions = [
        {
            label: __('all', 'rrze-synonym'),
            value: ''
        }
    ];

    if (!!tags) {
        Object.values(tags).forEach(tag => {
            tagoptions.push({
                label: tag.name,
                value: tag.slug,
            });
        });
    }

    const synonyms = useSelect((select) => {
        return select('core').getEntityRecords('postType', 'synonym', {per_page: -1, orderby: 'title', order: "asc"});
    }, []);

    const synonymoptions = [
        {
            label: __('all', 'rrze-synonym'),
            value: 0
        }
    ];

    if (!!synonyms) {
        Object.values(synonyms).forEach(synonym => {
            synonymoptions.push({
                label: synonym.title.rendered ? synonym.title.rendered : __('No title', 'rrze-synonym'),
                value: synonym.id,
            });
        });
    }

    const langoptions = [
        {
            label: __('all', 'rrze-faq'),
            value: ''
        },
        {
            label: __('German', 'rrze-faq'),
            value: 'de'
        },
        {

            label: __('English', 'rrze-faq'),
            value: 'en'
        },
        {

            label: __('French', 'rrze-faq'),
            value: 'fr'
        },
        {

            label: __('Spanish', 'rrze-faq'),
            value: 'es'
        },
        {
            label: __('Russian', 'rrze-faq'),
            value: 'ru'
        },
        {
            label: __('Chinese', 'rrze-faq'),
            value: 'zh'
        }
    ];


    const synonymstyleoptions = [
        {
            label: __('-- hidden --', 'rrze-synonym'),
            value: ''
        },
        {
            label: __('A - Z', 'rrze-synonym'),
            value: 'a-z'
        },
        {
            label: __('Tagcloud', 'rrze-synonym'),
            value: 'tagcloud'
        },
        {
            label: __('Tabs', 'rrze-synonym'),
            value: 'tabs'
        }
    ];

    const coloroptions = [
        {
            label: 'fau',
            value: 'fau'
        },
        {
            label: 'med',
            value: 'med'
        },
        {
            label: 'nat',
            value: 'nat'
        },
        {
            label: 'phil',
            value: 'phil'
        },
        {
            label: 'rw',
            value: 'rw'
        },
        {
            label: 'tf',
            value: 'tf'
        }
    ];

    const sortoptions = [
        {
            label: __('Title', 'rrze-synonym'),
            value: 'title'
        },
        {
            label: __('ID', 'rrze-synonym'),
            value: 'id'
        },
        {
            label: __('Sort field', 'rrze-synonym'),
            value: 'sortfield'
        }
    ];

    const orderoptions = [
        {
            label: __('ASC', 'rrze-synonym'),
            value: 'ASC'
        },
        {
            label: __('DESC', 'rrze-synonym'),
            value: 'DESC'
        }
    ];

    // console.log('edit.js attributes: ' + JSON.stringify(attributes));

    const onChangeID = (newValues) => {
        setSelectedIDs(newValues);
        setAttributes({id: String(newValues)})
    };

    return (
        <>
            <InspectorControls>
                <PanelBody>
                    <SelectControl
                        label={__(
                            "synonym",
                            'rrze-synonym'
                        )}
                        help={__('Show a selection of individual Synonyms', 'rrze-synonym')}
                        value={idstate}
                        options={synonymoptions}
                        onChange={onChangeID}
                        multiple
                    />
                    <SelectControl
                        label={__(
                            "Language",
                            'rrze-synonym'
                        )}
                        help={__('Show only Synonyms matching the selected language.', 'rrze-synonym')}
                        value={lang}
                        options={langoptions}
                        onChange={(value) => setAttributes({lang: value})}
                    />
                </PanelBody>
            </InspectorControls>
            <div {...blockProps}>
                <ServerSideRender
                    block="create-block/rrze-synonym"
                    attributes={attributes}
                />
            </div>
        </>
    );
}