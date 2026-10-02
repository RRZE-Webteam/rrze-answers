import {
	getCategories,
	parse,
	registerBlockType,
	serialize,
	setCategories,
	unregisterBlockType,
} from '@wordpress/blocks';
import {
	faqDeprecations,
	faqWidgetDeprecations,
	glossaryDeprecations,
	placeholderDeprecations,
	synonymDeprecations,
} from '../../src/editor/deprecations';
import faqMetadata from '../../src/blocks/faq/block.json';
import faqWidgetMetadata from '../../src/blocks/faq-widget/block.json';
import glossaryMetadata from '../../src/blocks/glossary/block.json';
import placeholderMetadata from '../../src/blocks/placeholder/block.json';
import synonymMetadata from '../../src/blocks/synonym/block.json';

const cases = [
	[
		faqMetadata,
		faqDeprecations,
		{ id: '12,34', category: 'one,two', hide_title: true },
		{ id: [ 12, 34 ], category: [ 'one', 'two' ], hide_title: true },
	],
	[
		glossaryMetadata,
		glossaryDeprecations,
		{ id: '12,34', tag: 'one,two', load_open: true },
		{ id: [ 12, 34 ], tag: [ 'one', 'two' ], load_open: true },
	],
	[
		placeholderMetadata,
		placeholderDeprecations,
		{ id: '12,34', lang: 'de' },
		{ id: [ 12, 34 ], lang: 'de' },
	],
	[
		synonymMetadata,
		synonymDeprecations,
		{ id: '12,34', lang: 'en', register: 'category' },
		{ id: [ 12, 34 ], lang: 'en' },
	],
	[
		faqWidgetMetadata,
		faqWidgetDeprecations,
		{ id: 12, catID: 34, hide_title: 1 },
		{ id: 12, catID: 34, hide_title: true },
	],
];

const originalCategories = getCategories();
beforeAll( () =>
	setCategories( [
		...originalCategories,
		{ slug: 'rrze', title: 'RRZE Answers' },
	] )
);
afterAll( () => setCategories( originalCategories ) );

describe.each(
	cases.map( ( [ metadata, deprecated, legacy, expected ] ) => ( {
		metadata,
		deprecated,
		legacy,
		expected,
	} ) )
)(
	'$metadata.name saved blocks',
	( { metadata, deprecated, legacy, expected } ) => {
		beforeAll( () => {
			registerBlockType( metadata.name, {
				...metadata,
				save: () => null,
				deprecated,
			} );
		} );

		afterAll( () => unregisterBlockType( metadata.name ) );

		it( 'preserves selections through parsing, migration and saving again', () => {
			const content = `<!-- wp:${ metadata.name } ${ JSON.stringify(
				legacy
			) } /-->`;
			const [ block ] = parse( content );
			expect( block.isValid ).toBe( true );
			expect( block.attributes ).toMatchObject( expected );
			const [ reopened ] = parse( serialize( [ block ] ) );
			expect( reopened.isValid ).toBe( true );
			expect( reopened.attributes ).toEqual( block.attributes );
		} );
	}
);
