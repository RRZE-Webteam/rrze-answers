import fs from 'fs';
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
import faq from '../../src/blocks/faq/block.json';
import glossary from '../../src/blocks/glossary/block.json';
import synonym from '../../src/blocks/synonym/block.json';
import placeholder from '../../src/blocks/placeholder/block.json';
import widget from '../../src/blocks/faq-widget/block.json';
import fixtures from '../fixtures/v1.4.6/blocks.json';
import legacySchemas from '../fixtures/v1.4.6/block-attributes.json';

const registrations = [
	[ faq, faqDeprecations ],
	[ glossary, glossaryDeprecations ],
	[ synonym, synonymDeprecations ],
	[ placeholder, placeholderDeprecations ],
	[ widget, faqWidgetDeprecations ],
];
const originalCategories = getCategories();
const migratedContent = {};

beforeAll( () => {
	setCategories( [ ...originalCategories, { slug: 'rrze', title: 'RRZE' } ] );
	for ( const [ metadata, deprecated ] of registrations ) {
		registerBlockType( metadata.name, {
			...metadata,
			save: () => null,
			deprecated,
		} );
	}
} );

afterAll( () => {
	for ( const [ metadata ] of registrations ) {
		unregisterBlockType( metadata.name );
	}
	setCategories( originalCategories );
	// The upgrade runner sends this actual Gutenberg serialization through REST.
	if ( process.env.RRZE_UPGRADE_OUTPUT ) {
		fs.writeFileSync(
			process.env.RRZE_UPGRADE_OUTPUT,
			JSON.stringify( migratedContent, null, 2 )
		);
	}
} );

it( 'uses deprecation schemas matching the v1.4.6 release', () => {
	for ( const [ metadata, deprecated ] of registrations ) {
		const name = metadata.name.split( '/' )[ 1 ];
		expect( deprecated[ 0 ].attributes ).toEqual( legacySchemas[ name ] );
	}
} );

it( 'retains every block on a mixed legacy page', () => {
	const content = fixtures
		.map( ( fixture ) => fixture.content )
		.join( '\n\n' );
	const blocks = parse( content );
	expect( blocks ).toHaveLength( fixtures.length );
	blocks.forEach( ( block, index ) => {
		expect( block.isValid ).toBe( true );
		expect( block.name ).toBe( fixtures[ index ].block );
		expect( block.attributes ).toMatchObject(
			fixtures[ index ].attributes
		);
	} );
	const saved = serialize( blocks );
	const reopened = parse( saved );
	expect( reopened.map( ( block ) => block.attributes ) ).toEqual(
		blocks.map( ( block ) => block.attributes )
	);
	expect( serialize( reopened ) ).toBe( saved );
	migratedContent[ 'mixed-blocks' ] = saved;
} );

it.each( fixtures )(
	'$name survives opening, saving and reopening',
	( fixture ) => {
		const blocks = parse( fixture.content );
		expect( blocks ).toHaveLength( 1 );
		expect( blocks[ 0 ].isValid ).toBe( true );
		expect( blocks[ 0 ].name ).toBe( fixture.block );
		expect( blocks[ 0 ].attributes ).toMatchObject( fixture.attributes );
		const saved = serialize( blocks );
		const reopened = parse( saved );
		expect( reopened[ 0 ].isValid ).toBe( true );
		expect( reopened[ 0 ].attributes ).toEqual( blocks[ 0 ].attributes );
		expect( serialize( reopened ) ).toBe( saved );
		migratedContent[ fixture.name ] = saved;
	}
);
