import {
	hasLegacyFaqAttributes,
	hasLegacyFaqWidgetAttributes,
	hasLegacyGlossaryAttributes,
	hasLegacyPlaceholderAttributes,
	hasLegacySynonymAttributes,
	migrateFaqAttributes,
	migrateFaqWidgetAttributes,
	migrateGlossaryAttributes,
	migratePlaceholderAttributes,
	migrateSynonymAttributes,
} from './migrations/legacy-attributes';

import type {
	Block,
	BlockAttribute,
	BlockDeprecation,
} from '@wordpress/blocks';

type UnknownAttributes = Record< string, unknown >;
type LegacyAttributeSchema = Record< string, BlockAttribute >;

interface DeprecationData {
	blockNode?: {
		attrs?: UnknownAttributes;
	};
}

type Migration< Attributes extends UnknownAttributes > = (
	attributes: UnknownAttributes
) => Attributes;

type LegacyPredicate = ( attributes: UnknownAttributes ) => boolean;

const save = () => null;
const supports = { html: false };

const faqAttributes: LegacyAttributeSchema = {
	glossary: { type: 'string' },
	glossarystyle: { type: 'string' },
	category: { type: 'string' },
	tag: { type: 'string' },
	id: { type: 'string' },
	hide_accordion: { type: 'boolean' },
	hide_title: { type: 'boolean' },
	masonry: { type: 'boolean' },
	search: { type: 'boolean', default: false },
	color: { type: 'string' },
	style: { type: 'string', default: 'light' },
	additional_class: { type: 'string' },
	lang: { type: 'string' },
	sort: { type: 'string' },
	order: { type: 'string' },
	hstart: { type: 'number', default: 2 },
};

const glossaryAttributes: LegacyAttributeSchema = {
	category: { type: 'string' },
	tag: { type: 'string' },
	id: { type: 'string' },
	register: { type: 'string' },
	registerstyle: { type: 'string' },
	hide_accordion: { type: 'boolean' },
	hide_title: { type: 'boolean' },
	masonry: { type: 'boolean' },
	search: { type: 'boolean', default: false },
	expand_all_link: { type: 'boolean' },
	load_open: { type: 'boolean' },
	color: { type: 'string' },
	style: { type: 'string', default: 'light' },
	additional_class: { type: 'string' },
	lang: { type: 'string' },
	sort: { type: 'string' },
	order: { type: 'string' },
	hstart: { type: 'number', default: 2 },
};

const synonymAttributes: LegacyAttributeSchema = {
	register: { type: 'string' },
	registerstyle: { type: 'string' },
	category: { type: 'string' },
	tag: { type: 'string' },
	id: { type: 'string' },
	hide_accordion: { type: 'boolean' },
	hide_title: { type: 'boolean' },
	expand_all_link: { type: 'boolean' },
	load_open: { type: 'boolean' },
	color: { type: 'string' },
	additional_class: { type: 'string' },
	lang: { type: 'string' },
	sort: { type: 'string' },
	order: { type: 'string' },
	hstart: { type: 'number' },
};

const placeholderAttributes: LegacyAttributeSchema = {
	id: { type: 'string' },
	additional_class: { type: 'string' },
	lang: { type: 'string' },
	sort: { type: 'string' },
	order: { type: 'string' },
};

const faqWidgetAttributes: LegacyAttributeSchema = {
	id: { type: 'integer', default: 0 },
	catID: { type: 'integer', default: 0 },
	hide_title: { type: 'integer', default: 0 },
};

function rawAttributes(
	attributes: UnknownAttributes,
	data?: DeprecationData
): UnknownAttributes {
	return data?.blockNode?.attrs || attributes;
}

function createDeprecation< Attributes extends UnknownAttributes >(
	attributes: LegacyAttributeSchema,
	migrate: Migration< Attributes >,
	hasLegacyAttributes: LegacyPredicate
): BlockDeprecation< Attributes > {
	return {
		attributes,
		supports,
		save,
		migrate,
		isEligible(
			currentAttributes: UnknownAttributes,
			_innerBlocks: Block[],
			data?: DeprecationData
		) {
			return hasLegacyAttributes(
				rawAttributes( currentAttributes, data )
			);
		},
	};
}

export const faqDeprecations = [
	createDeprecation(
		faqAttributes,
		migrateFaqAttributes,
		hasLegacyFaqAttributes
	),
];

export const glossaryDeprecations = [
	createDeprecation(
		glossaryAttributes,
		migrateGlossaryAttributes,
		hasLegacyGlossaryAttributes
	),
];

export const synonymDeprecations = [
	createDeprecation(
		synonymAttributes,
		migrateSynonymAttributes,
		hasLegacySynonymAttributes
	),
];

export const placeholderDeprecations = [
	createDeprecation(
		placeholderAttributes,
		migratePlaceholderAttributes,
		hasLegacyPlaceholderAttributes
	),
];

export const faqWidgetDeprecations = [
	createDeprecation(
		faqWidgetAttributes,
		migrateFaqWidgetAttributes,
		hasLegacyFaqWidgetAttributes
	),
];
