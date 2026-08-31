import { BlockControls, HeadingLevelDropdown } from '@wordpress/block-editor';
import { PanelBody, SelectControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import type { BlockEditProps } from '@wordpress/blocks';
import type { SelectOption } from '../hooks/use-entity-options';

interface HeadingLevelToolbarProps {
	value: number;
	onChange: ( value: number ) => void;
}

interface LanguageControlProps {
	value: string;
	onChange: ( value: string ) => void;
	help?: string;
}

interface AppearanceFields extends Record< string, unknown > {
	hide_accordion: boolean;
	hide_title: boolean;
	search: boolean;
}

interface AppearancePanelProps< Attributes extends AppearanceFields > {
	attributes: Attributes;
	setAttributes: BlockEditProps< Attributes >[ 'setAttributes' ];
	indexAttribute: keyof Attributes & string;
	indexLabel: string;
	showSearch?: boolean;
	allowGroupedIndexStyles?: boolean;
	elementsTabsAvailable?: boolean;
}

interface SortingFields extends Record< string, unknown > {
	order: string;
	sort: string;
}

interface SortingPanelProps< Attributes extends SortingFields > {
	order: string;
	sort: string;
	setAttributes: BlockEditProps< Attributes >[ 'setAttributes' ];
}

export function getLanguageOptions(): SelectOption[] {
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

export function getGroupingOptions(): SelectOption[] {
	return [
		{ label: __( 'none', 'rrze-answers' ), value: '' },
		{ label: __( 'Categories', 'rrze-answers' ), value: 'category' },
		{ label: __( 'Tags', 'rrze-answers' ), value: 'tag' },
	];
}

function getIndexStyleOptions(
	allowGroupedStyles: boolean,
	elementsTabsAvailable: boolean
): SelectOption[] {
	const options: SelectOption[] = [
		{ label: __( 'A - Z', 'rrze-answers' ), value: 'a-z' },
		{
			label: __( 'Tagcloud', 'rrze-answers' ),
			value: 'tagcloud',
			disabled: ! allowGroupedStyles,
		},
	];

	if ( elementsTabsAvailable ) {
		options.push( {
			label: __( 'Tabs', 'rrze-answers' ),
			value: 'tabs',
			disabled: ! allowGroupedStyles,
		} );
	}

	options.push( {
		label: __( '-- hidden --', 'rrze-answers' ),
		value: '',
	} );

	return options;
}

export function HeadingLevelToolbar( {
	value,
	onChange,
}: HeadingLevelToolbarProps ) {
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

export function LanguageControl( {
	value,
	onChange,
	help,
}: LanguageControlProps ) {
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

export function AppearancePanel< Attributes extends AppearanceFields >( {
	attributes,
	setAttributes,
	indexAttribute,
	indexLabel,
	showSearch = false,
	allowGroupedIndexStyles = false,
	elementsTabsAvailable = false,
}: AppearancePanelProps< Attributes > ) {
	const {
		hide_accordion: hideAccordion,
		hide_title: hideTitle,
		search,
	} = attributes;
	let indexHelp: string | undefined;

	if ( ! allowGroupedIndexStyles ) {
		indexHelp = elementsTabsAvailable
			? __(
					'Tabs and Tagcloud require category or tag grouping.',
					'rrze-answers'
			  )
			: __(
					'Tagcloud requires category or tag grouping.',
					'rrze-answers'
			  );
	}

	return (
		<PanelBody
			title={ __( 'Appearance', 'rrze-answers' ) }
			icon={ 'admin-appearance' as never }
			initialOpen={ false }
		>
			<SelectControl
				label={ indexLabel }
				help={ indexHelp }
				value={ String( attributes[ indexAttribute ] || '' ) }
				options={ getIndexStyleOptions(
					allowGroupedIndexStyles,
					elementsTabsAvailable
				) }
				onChange={ ( value ) =>
					setAttributes( {
						[ indexAttribute ]: value,
					} as Partial< Attributes > )
				}
			/>
			{ showSearch && (
				<ToggleControl
					checked={ search }
					label={ __( 'Show search field', 'rrze-answers' ) }
					help={ __(
						'Shows a search input above the list to filter entries.',
						'rrze-answers'
					) }
					onChange={ ( value ) =>
						setAttributes( {
							search: value,
						} as Partial< Attributes > )
					}
				/>
			) }
			<ToggleControl
				checked={ hideAccordion }
				label={ __( 'Hide accordion', 'rrze-answers' ) }
				onChange={ ( value ) =>
					setAttributes( {
						hide_accordion: value,
					} as Partial< Attributes > )
				}
			/>
			<ToggleControl
				checked={ hideTitle }
				label={ __( 'Hide title', 'rrze-answers' ) }
				onChange={ ( value ) =>
					setAttributes( {
						hide_title: value,
					} as Partial< Attributes > )
				}
			/>
		</PanelBody>
	);
}

export function SortingPanel< Attributes extends SortingFields >( {
	order,
	sort,
	setAttributes,
}: SortingPanelProps< Attributes > ) {
	return (
		<PanelBody title={ __( 'Sorting options', 'rrze-answers' ) }>
			<SelectControl
				label={ __( 'Sort', 'rrze-answers' ) }
				value={ sort || '' }
				options={
					[
						{
							label: __( 'Title', 'rrze-answers' ),
							value: 'title',
						},
						{ label: __( 'ID', 'rrze-answers' ), value: 'id' },
						{
							label: __( 'Sort field', 'rrze-answers' ),
							value: 'sortfield',
						},
					] as SelectOption[]
				}
				onChange={ ( value ) =>
					setAttributes( { sort: value } as Partial< Attributes > )
				}
			/>
			<SelectControl
				label={ __( 'Order', 'rrze-answers' ) }
				value={ order || '' }
				options={
					[
						{ label: __( 'ASC', 'rrze-answers' ), value: 'ASC' },
						{ label: __( 'DESC', 'rrze-answers' ), value: 'DESC' },
					] as SelectOption[]
				}
				onChange={ ( value ) =>
					setAttributes( { order: value } as Partial< Attributes > )
				}
			/>
		</PanelBody>
	);
}
