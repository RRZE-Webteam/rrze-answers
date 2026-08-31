type UnknownAttributes = Record< string, unknown >;

export interface FaqAttributes extends Record< string, unknown > {
	glossary: string;
	glossarystyle: string;
	category: string[];
	tag: string[];
	id: number[];
	hide_accordion: boolean;
	hide_title: boolean;
	masonry: boolean;
	search: boolean;
	color: string;
	style: string;
	additional_class: string;
	lang: string;
	sort: string;
	order: string;
	hstart: number;
}

export interface GlossaryAttributes extends Record< string, unknown > {
	category: string[];
	tag: string[];
	id: number[];
	register: string;
	registerstyle: string;
	hide_accordion: boolean;
	hide_title: boolean;
	masonry: boolean;
	search: boolean;
	expand_all_link: boolean;
	load_open: boolean;
	color: string;
	style: string;
	additional_class: string;
	lang: string;
	sort: string;
	order: string;
	hstart: number;
}

export interface ListBlockAttributes extends Record< string, unknown > {
	id: number[];
	additional_class: string;
	lang: string;
	sort: string;
	order: string;
}

export interface SynonymAttributes extends ListBlockAttributes {
	category: string[];
	tag: string[];
	hide_accordion: boolean;
	hide_title: boolean;
	expand_all_link: boolean;
	load_open: boolean;
	color: string;
	hstart: number;
}

export interface FaqWidgetAttributes extends Record< string, unknown > {
	id: number;
	catID: number;
	hide_title: boolean;
}

function valuesFromUnknown( value: unknown ): unknown[] {
	if ( Array.isArray( value ) ) {
		return value;
	}

	if ( value === undefined || value === null || value === '' ) {
		return [];
	}

	return String( value ).split( ',' );
}

export function normalizeStringList( value: unknown ): string[] {
	return [
		...new Set(
			valuesFromUnknown( value )
				.map( ( item ) => String( item ).trim() )
				.filter( Boolean )
		),
	];
}

export function normalizeIntegerList( value: unknown ): number[] {
	return [
		...new Set(
			valuesFromUnknown( value )
				.map( ( item ) => Number.parseInt( String( item ), 10 ) )
				.filter( ( item ) => Number.isInteger( item ) && item > 0 )
		),
	];
}

export function normalizeBoolean( value: unknown ): boolean {
	if ( typeof value === 'string' ) {
		return [ '1', 'true', 'yes', 'on' ].includes( value.toLowerCase() );
	}

	return value === true || value === 1;
}

function stringValue( value: unknown, fallback = '' ): string {
	return typeof value === 'string' ? value : fallback;
}

function integerValue( value: unknown, fallback = 0 ): number {
	const integer = Number.parseInt( String( value ?? '' ), 10 );
	return Number.isInteger( integer ) ? integer : fallback;
}

export function migrateFaqAttributes(
	attributes: UnknownAttributes
): FaqAttributes {
	return {
		glossary: stringValue( attributes.glossary ),
		glossarystyle: stringValue( attributes.glossarystyle ),
		category: normalizeStringList( attributes.category ),
		tag: normalizeStringList( attributes.tag ),
		id: normalizeIntegerList( attributes.id ),
		hide_accordion: normalizeBoolean( attributes.hide_accordion ),
		hide_title: normalizeBoolean( attributes.hide_title ),
		masonry: normalizeBoolean( attributes.masonry ),
		search: normalizeBoolean( attributes.search ),
		color: stringValue( attributes.color ),
		style: stringValue( attributes.style, 'light' ),
		additional_class: stringValue( attributes.additional_class ),
		lang: stringValue( attributes.lang ),
		sort: stringValue( attributes.sort, 'title' ),
		order: stringValue( attributes.order, 'ASC' ),
		hstart: integerValue( attributes.hstart, 2 ),
	};
}

export function migrateGlossaryAttributes(
	attributes: UnknownAttributes
): GlossaryAttributes {
	return {
		category: normalizeStringList( attributes.category ),
		tag: normalizeStringList( attributes.tag ),
		id: normalizeIntegerList( attributes.id ),
		register: stringValue( attributes.register ),
		registerstyle: stringValue( attributes.registerstyle ),
		hide_accordion: normalizeBoolean( attributes.hide_accordion ),
		hide_title: normalizeBoolean( attributes.hide_title ),
		masonry: normalizeBoolean( attributes.masonry ),
		search: normalizeBoolean( attributes.search ),
		expand_all_link: normalizeBoolean( attributes.expand_all_link ),
		load_open: normalizeBoolean( attributes.load_open ),
		color: stringValue( attributes.color ),
		style: stringValue( attributes.style, 'light' ),
		additional_class: stringValue( attributes.additional_class ),
		lang: stringValue( attributes.lang ),
		sort: stringValue( attributes.sort, 'title' ),
		order: stringValue( attributes.order, 'ASC' ),
		hstart: integerValue( attributes.hstart, 2 ),
	};
}

export function migratePlaceholderAttributes(
	attributes: UnknownAttributes
): ListBlockAttributes {
	return {
		id: normalizeIntegerList( attributes.id ),
		additional_class: stringValue( attributes.additional_class ),
		lang: stringValue( attributes.lang ),
		sort: stringValue( attributes.sort, 'title' ),
		order: stringValue( attributes.order, 'ASC' ),
	};
}

export function migrateSynonymAttributes(
	attributes: UnknownAttributes
): SynonymAttributes {
	return {
		category: normalizeStringList( attributes.category ),
		tag: normalizeStringList( attributes.tag ),
		id: normalizeIntegerList( attributes.id ),
		hide_accordion: normalizeBoolean( attributes.hide_accordion ),
		hide_title: normalizeBoolean( attributes.hide_title ),
		expand_all_link: normalizeBoolean( attributes.expand_all_link ),
		load_open: normalizeBoolean( attributes.load_open ),
		color: stringValue( attributes.color ),
		additional_class: stringValue( attributes.additional_class ),
		lang: stringValue( attributes.lang ),
		sort: stringValue( attributes.sort, 'title' ),
		order: stringValue( attributes.order, 'ASC' ),
		hstart: integerValue( attributes.hstart, 2 ),
	};
}

export function migrateFaqWidgetAttributes(
	attributes: UnknownAttributes
): FaqWidgetAttributes {
	return {
		id: integerValue( attributes.id ),
		catID: integerValue( attributes.catID ),
		hide_title: normalizeBoolean( attributes.hide_title ),
	};
}

function hasNonArrayValue(
	attributes: UnknownAttributes,
	attributeNames: string[]
): boolean {
	return attributeNames.some(
		( name ) => name in attributes && ! Array.isArray( attributes[ name ] )
	);
}

function hasNonBooleanValue(
	attributes: UnknownAttributes,
	attributeNames: string[]
): boolean {
	return attributeNames.some(
		( name ) =>
			name in attributes && typeof attributes[ name ] !== 'boolean'
	);
}

export function hasLegacyFaqAttributes(
	attributes: UnknownAttributes
): boolean {
	return (
		hasNonArrayValue( attributes, [ 'category', 'tag', 'id' ] ) ||
		hasNonBooleanValue( attributes, [
			'hide_accordion',
			'hide_title',
			'masonry',
			'search',
		] )
	);
}

export function hasLegacyGlossaryAttributes(
	attributes: UnknownAttributes
): boolean {
	return (
		hasNonArrayValue( attributes, [ 'category', 'tag', 'id' ] ) ||
		hasNonBooleanValue( attributes, [
			'hide_accordion',
			'hide_title',
			'masonry',
			'search',
			'expand_all_link',
			'load_open',
		] )
	);
}

export function hasLegacyPlaceholderAttributes(
	attributes: UnknownAttributes
): boolean {
	return hasNonArrayValue( attributes, [ 'id' ] );
}

export function hasLegacySynonymAttributes(
	attributes: UnknownAttributes
): boolean {
	return (
		'register' in attributes ||
		'registerstyle' in attributes ||
		hasNonArrayValue( attributes, [ 'category', 'tag', 'id' ] ) ||
		hasNonBooleanValue( attributes, [
			'hide_accordion',
			'hide_title',
			'expand_all_link',
			'load_open',
		] )
	);
}

export function hasLegacyFaqWidgetAttributes(
	attributes: UnknownAttributes
): boolean {
	return hasNonBooleanValue( attributes, [ 'hide_title' ] );
}
