import { BlockControls, HeadingLevelDropdown } from '@wordpress/block-editor';
import { PanelBody, SelectControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export function getLanguageOptions() {
	return [
		{ label: __( 'all', 'rrze-answers' ), value: '' },
		{ label: __( 'German', 'rrze-answers' ), value: 'de' },
		{ label: __( 'English', 'rrze-answers' ), value: 'en' },
		{ label: __( 'French', 'rrze-answers' ), value: 'fr' },
		{ label: __( 'Spanish', 'rrze-answers' ), value: 'es' },
		{ label: __( 'Russian', 'rrze-answers' ), value: 'ru' },
		{ label: __( 'Chinese', 'rrze-answers' ), value: 'zh' },
	];
}

export function getGroupingOptions() {
	return [
		{ label: __( 'none', 'rrze-answers' ), value: '' },
		{ label: __( 'Categories', 'rrze-answers' ), value: 'category' },
		{ label: __( 'Tags', 'rrze-answers' ), value: 'tag' },
	];
}

function getIndexStyleOptions() {
	return [
		{ label: __( 'A - Z', 'rrze-answers' ), value: 'a-z' },
		{ label: __( 'Tagcloud', 'rrze-answers' ), value: 'tagcloud' },
		{ label: __( 'Tabs', 'rrze-answers' ), value: 'tabs' },
		{ label: __( '-- hidden --', 'rrze-answers' ), value: '' },
	];
}

function getColorOptions() {
	return [ 'fau', 'med', 'nat', 'phil', 'rw', 'tf' ].map( ( value ) => ( {
		label: value,
		value,
	} ) );
}

function getAccordionStyleOptions( allowEmptyStyle ) {
	const options = [
		{ label: 'light', value: 'light' },
		{ label: 'dark', value: 'dark' },
	];

	return allowEmptyStyle
		? [ { label: __( 'none', 'rrze-answers' ), value: '' }, ...options ]
		: options;
}

export function HeadingLevelToolbar( { value, onChange } ) {
	return (
		<BlockControls>
			<HeadingLevelDropdown
				options={ [ 2, 3, 4, 5, 6 ] }
				value={ value }
				onChange={ onChange }
			/>
		</BlockControls>
	);
}

export function LanguageControl( { value, onChange, help } ) {
	return (
		<SelectControl
			label={ __( 'Language', 'rrze-answers' ) }
			help={ help }
			value={ value || '' }
			options={ getLanguageOptions() }
			onChange={ onChange }
		/>
	);
}

export function AppearancePanel( {
	attributes,
	setAttributes,
	indexAttribute,
	indexLabel,
	showSearch = false,
	allowEmptyStyle = false,
} ) {
	const {
		color,
		hide_accordion: hideAccordion,
		hide_title: hideTitle,
		masonry,
		search,
		style,
	} = attributes;

	return (
		<PanelBody
			title={ __( 'Appearance', 'rrze-answers' ) }
			icon="admin-appearance"
			initialOpen={ false }
		>
			<SelectControl
				label={ indexLabel }
				value={ attributes[ indexAttribute ] || '' }
				options={ getIndexStyleOptions() }
				onChange={ ( value ) =>
					setAttributes( { [ indexAttribute ]: value } )
				}
			/>
			{ showSearch && (
				<ToggleControl
					checked={ !! search }
					label={ __( 'Show search field', 'rrze-answers' ) }
					help={ __(
						'Shows a search input above the list to filter entries.',
						'rrze-answers'
					) }
					onChange={ ( value ) => setAttributes( { search: value } ) }
				/>
			) }
			<ToggleControl
				checked={ !! hideAccordion }
				label={ __( 'Hide accordion', 'rrze-answers' ) }
				onChange={ ( value ) =>
					setAttributes( { hide_accordion: value } )
				}
			/>
			<ToggleControl
				checked={ !! masonry }
				label={ __( 'Grid', 'rrze-answers' ) }
				onChange={ ( value ) => setAttributes( { masonry: value } ) }
			/>
			<SelectControl
				label={ __( 'Accordion style', 'rrze-answers' ) }
				value={ style || 'light' }
				options={ getAccordionStyleOptions( allowEmptyStyle ) }
				onChange={ ( value ) => setAttributes( { style: value } ) }
			/>
			<SelectControl
				label={ __( 'Color', 'rrze-answers' ) }
				value={ color || '' }
				options={ getColorOptions() }
				onChange={ ( value ) => setAttributes( { color: value } ) }
			/>
			<ToggleControl
				checked={ !! hideTitle }
				label={ __( 'Hide title', 'rrze-answers' ) }
				onChange={ ( value ) => setAttributes( { hide_title: value } ) }
			/>
		</PanelBody>
	);
}

export function SortingPanel( { order, sort, setAttributes } ) {
	return (
		<PanelBody title={ __( 'Sorting options', 'rrze-answers' ) }>
			<SelectControl
				label={ __( 'Sort', 'rrze-answers' ) }
				value={ sort || '' }
				options={ [
					{ label: __( 'Title', 'rrze-answers' ), value: 'title' },
					{ label: __( 'ID', 'rrze-answers' ), value: 'id' },
					{
						label: __( 'Sort field', 'rrze-answers' ),
						value: 'sortfield',
					},
				] }
				onChange={ ( value ) => setAttributes( { sort: value } ) }
			/>
			<SelectControl
				label={ __( 'Order', 'rrze-answers' ) }
				value={ order || '' }
				options={ [
					{ label: __( 'ASC', 'rrze-answers' ), value: 'ASC' },
					{ label: __( 'DESC', 'rrze-answers' ), value: 'DESC' },
				] }
				onChange={ ( value ) => setAttributes( { order: value } ) }
			/>
		</PanelBody>
	);
}
