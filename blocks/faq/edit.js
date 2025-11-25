/**
 * Retrieves the translation of text.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-i18n/
 */
import { __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import {
	InspectorControls,
	useBlockProps,
	HeadingLevelDropdown,
	BlockControls,
} from '@wordpress/block-editor';
import {
	PanelBody,
	ToggleControl,
	SelectControl,
	RangeControl,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';


function buildCategoryOptions(categories) {
	const map = new Map();
	const roots = [];

	categories.forEach((cat) => {
		cat.children = [];
		map.set(cat.id, cat);
	});

	categories.forEach((cat) => {
		if (cat.parent && map.has(cat.parent)) {
			map.get(cat.parent).children.push(cat);
		} else {
			roots.push(cat);
		}
	});

	const sortByName = (list) =>
		list.sort((a, b) =>
			a.name.localeCompare(b.name, undefined, { sensitivity: 'base' })
		);

	const flatten = (list, depth = 0) => {
		const result = [];
		sortByName(list).forEach((cat) => {
			result.push({
				label: `${'-'.repeat(depth)} ${cat.name}`.trim(),
				value: cat.slug,
			});
			result.push(...flatten(cat.children, depth + 1));
		});
		return result;
	};

	return flatten(roots);
}


export default function Edit({ attributes, setAttributes }) {
	const {
		category,
		tag,
		id,
		hstart,
		order,
		sort,
		lang,
		additional_class,
		color,
		style,
		masonry,
		hide_title,
		hide_accordion,
		glossarystyle,
		glossary,
	} = attributes;
	const blockProps = useBlockProps();
	const [categorystate, setSelectedCategories] = useState(['']);
	const [tagstate, setSelectedTags] = useState(['']);
	const [idstate, setSelectedIDs] = useState(['']);

	const categories = useSelect((select) => {
		return select('core').getEntityRecords('taxonomy', 'rrze_faq_category', {
			per_page: -1,
			orderby: 'name',
			order: 'asc',
			status: 'publish',
			_fields: 'id,name,slug,parent',
		});
	}, []);

	const categoryoptions = [
		{
			label: __('all', 'rrze-answers'),
			value: '',
		},
	];

	if (Array.isArray(categories)) {
		categoryoptions.push(...buildCategoryOptions(categories));
	}

	const tags = useSelect((select) => {
		return select('core').getEntityRecords('taxonomy', 'rrze_faq_tag', {
			per_page: -1,
			orderby: 'name',
			order: 'asc',
			status: 'publish',
			_fields: 'id,name,slug',
		});
	}, []);

	const tagoptions = [
		{
			label: __('all', 'rrze-answers'),
			value: '',
		},
	];

	if (!!tags) {
		Object.values(tags).forEach((tag) => {
			tagoptions.push({
				label: tag.name,
				value: tag.slug,
			});
		});
	}

	const faqs = useSelect((select) => {
		return select('core').getEntityRecords('postType', 'rrze_faq', {
			per_page: -1,
			orderby: 'title',
			order: 'asc',
			status: 'publish',
			_fields: 'id,title.rendered',
		});
	}, []);

	const faqoptions = [
		{
			label: __('all', 'rrze-answers'),
			value: 0,
		},
	];

	if (!!faqs) {
		Object.values(faqs).forEach((faq) => {
			faqoptions.push({
				label: faq.title.rendered
					? faq.title.rendered
					: __('No title', 'rrze-answers'),
				value: faq.id,
			});
		});
	}

	const langoptions = [
		{
			label: __('all', 'rrze-answers'),
			value: '',
		},
		{
			label: __('German', 'rrze-answers'),
			value: 'de',
		},
		{
			label: __('English', 'rrze-answers'),
			value: 'en',
		},
		{
			label: __('French', 'rrze-answers'),
			value: 'fr',
		},
		{
			label: __('Spanish', 'rrze-answers'),
			value: 'es',
		},
		{
			label: __('Russian', 'rrze-answers'),
			value: 'ru',
		},
		{
			label: __('Chinese', 'rrze-answers'),
			value: 'zh',
		},
	];

	const glossaryoptions = [
		{
			label: __('none', 'rrze-answers'),
			value: '',
		},
		{
			label: __('Categories', 'rrze-answers'),
			value: 'category',
		},
		{
			label: __('Tags', 'rrze-answers'),
			value: 'tag',
		},
	];

	const glossarystyleoptions = [
		{
			label: __('A - Z', 'rrze-answers'),
			value: 'a-z',
		},
		{
			label: __('Tagcloud', 'rrze-answers'),
			value: 'tagcloud',
		},
		{
			label: __('Tabs', 'rrze-answers'),
			value: 'tabs',
		},
		{
			label: __('-- hidden --', 'rrze-answers'),
			value: '',
		},
	];

	const coloroptions = [
		{
			label: 'fau',
			value: 'fau',
		},
		{
			label: 'med',
			value: 'med',
		},
		{
			label: 'nat',
			value: 'nat',
		},
		{
			label: 'phil',
			value: 'phil',
		},
		{
			label: 'rw',
			value: 'rw',
		},
		{
			label: 'tf',
			value: 'tf',
		},
	];

	const styleoptions = [
		{
			label: __('none', 'rrze-answers'),
			value: '',
		},
		{
			label: 'light',
			value: 'light',
		},
		{
			label: 'dark',
			value: 'dark',
		},
	];

	const sortoptions = [
		{
			label: __('Title', 'rrze-answers'),
			value: 'title',
		},
		{
			label: __('ID', 'rrze-answers'),
			value: 'id',
		},
		{
			label: __('Sort field', 'rrze-answers'),
			value: 'sortfield',
		},
	];

	const orderoptions = [
		{
			label: __('ASC', 'rrze-answers'),
			value: 'ASC',
		},
		{
			label: __('DESC', 'rrze-answers'),
			value: 'DESC',
		},
	];

	//////// onChange handlers /////////
	const onChangeCategory = (newValues) => {
		setSelectedCategories(newValues);
		setAttributes({ category: String(newValues) });
	};

	const onChangeTag = (newValues) => {
		setSelectedTags(newValues);
		setAttributes({ tag: String(newValues) });
	};

	const onChangeID = (newValues) => {
		setSelectedIDs(newValues);
		setAttributes({ id: String(newValues) });
	};

	return (
		<>
			<BlockControls>
				<HeadingLevelDropdown
					options={[2, 3, 4, 5, 6]}
					value={hstart}
					onChange={(value) => setAttributes({ hstart: value })}
				/>
			</BlockControls>

			<InspectorControls>
				<PanelBody
					title={__('Filter options', 'rrze-answers')}
					header={__('Filter the FAQ-entries.', 'rrze-answers')}
				>
					<SelectControl
						label={__('Categories', 'rrze-answers')}
						help={__(
							'Only show FAQ-entries with these selected categories.',
							'rrze-answers'
						)}
						value={categorystate}
						options={categoryoptions}
						onChange={onChangeCategory}
						multiple
					/>
					<SelectControl
						label={__('Tags', 'rrze-answers')}
						help={__(
							'Only show FAQ-entries with these selected tags.',
							'rrze-answers'
						)}
						value={tagstate}
						options={tagoptions}
						onChange={onChangeTag}
						multiple
					/>
					<SelectControl
						label={__('Single FAQ-Entries', 'rrze-answers')}
						help={__(
							'Only show these FAQ-entries.',
							'rrze-answers'
						)}
						value={idstate}
						options={faqoptions}
						onChange={onChangeID}
						multiple
					/>
					<SelectControl
						label={__('Language', 'rrze-answers')}
						help={__(
							'Only show FAQ-entries in this language.',
							'rrze-answers'
						)}
						value={lang}
						options={langoptions}
						onChange={(value) =>
							setAttributes({ lang: value })
						}
					/>
					<SelectControl
						label={__('Group Glossary Content by', 'rrze-answers')}
						help={__(
							'Group FAQ-entries by categories or tags.',
							'rrze-answers'
						)}
						value={glossary}
						options={glossaryoptions}
						onChange={(value) =>
							setAttributes({ glossary: value })
						}
					/>
				</PanelBody>
				<PanelBody
					title={__('Appearance', 'rrze-answers')}
					name={__('Appearance', 'rrze-answers')}
					icon="admin-appearance"
					initialOpen={false}
				>
					<SelectControl
						label={__('Glossary style', 'rrze-answers')}
						options={glossarystyleoptions}
						onChange={(value) =>
							setAttributes({ glossarystyle: value })
						}
					/>
					{(!glossary || glossary === 'none') && (
						<>
							<ToggleControl
								checked={!!hide_accordion}
								label={__('Hide accordion', 'rrze-answers')}
								onChange={() =>
									setAttributes({
										hide_accordion: !hide_accordion,
									})
								}
							/>
								<>
									<ToggleControl
										checked={!!masonry}
										label={__('Grid', 'rrze-answers')}
										onChange={() =>
											setAttributes({
												masonry: !masonry,
											})
										}
									/>
									<SelectControl
										label={__(
											'Accordion-Style',
											'rrze-answers'
										)}
										options={styleoptions}
										onChange={(value) =>
											setAttributes({ style: value })
										}
									/>
									<SelectControl
										label={__('Color', 'rrze-answers')}
										options={coloroptions}
										onChange={(value) =>
											setAttributes({ color: value })
										}
									/>
								</>
								<ToggleControl
									checked={!!hide_title}
									label={__('Hide title', 'rrze-answers')}
									onChange={() =>
										setAttributes({
											hide_title: !hide_title,
										})
									}
								/>
						</>
					)}
				</PanelBody>
				<PanelBody title={__('Sorting options', 'rrze-answers')}>
					<SelectControl
						label={__('Sort', 'rrze-answers')}
						options={sortoptions}
						onChange={(value) =>
							setAttributes({ sort: value })
						}
					/>
					<SelectControl
						label={__('Order', 'rrze-answers')}
						options={orderoptions}
						onChange={(value) =>
							setAttributes({ order: value })
						}
					/>
				</PanelBody>
			</InspectorControls>
			<div {...blockProps}>
				<ServerSideRender
					block="rrze-answers/faq"
					attributes={attributes}
				/>
			</div>
		</>
	);
}
