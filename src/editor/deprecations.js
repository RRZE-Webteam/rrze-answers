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

const save = () => null;
const supports = { html: false };

const faqAttributes = {
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

const glossaryAttributes = {
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

const synonymAttributes = {
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

const placeholderAttributes = {
	id: { type: 'string' },
	additional_class: { type: 'string' },
	lang: { type: 'string' },
	sort: { type: 'string' },
	order: { type: 'string' },
};

const faqWidgetAttributes = {
	id: { type: 'integer', default: 0 },
	catID: { type: 'integer', default: 0 },
	hide_title: { type: 'integer', default: 0 },
};

function rawAttributes( attributes, data ) {
	return data?.blockNode?.attrs || attributes;
}

function createDeprecation( attributes, migrate, hasLegacyAttributes ) {
	return {
		attributes,
		supports,
		save,
		migrate,
		isEligible( currentAttributes, _innerBlocks, data ) {
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
