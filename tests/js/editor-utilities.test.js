jest.mock(
	'@wordpress/data',
	() => ( {
		useSelect: jest.fn(),
	} ),
	{ virtual: true }
);

import { buildHierarchicalTermOptions } from '../../src/editor/hooks/use-entity-options';
import { faqDeprecations } from '../../src/editor/deprecations';
import {
	hasLegacyFaqAttributes,
	migrateFaqAttributes,
	normalizeIntegerList,
	normalizeStringList,
} from '../../src/editor/migrations/legacy-attributes';

describe( 'buildHierarchicalTermOptions', () => {
	it( 'sorts and indents terms without mutating core-data records', () => {
		const terms = [
			{ id: 3, name: 'Zulu', slug: 'zulu', parent: 0 },
			{ id: 2, name: 'Child', slug: 'child', parent: 1 },
			{ id: 1, name: 'Alpha', slug: 'alpha', parent: 0 },
		];
		const originalTerms = JSON.parse( JSON.stringify( terms ) );

		expect( buildHierarchicalTermOptions( terms ) ).toEqual( [
			{ label: 'Alpha', value: 'alpha' },
			{ label: '— Child', value: 'child' },
			{ label: 'Zulu', value: 'zulu' },
		] );
		expect( terms ).toEqual( originalTerms );
	} );
} );

describe( 'typed attribute normalization', () => {
	it.each( [
		[ 'one,two', [ 'one', 'two' ] ],
		[ ' one, two, one, ', [ 'one', 'two' ] ],
		[ '', [] ],
		[ undefined, [] ],
		[ [], [] ],
		[
			[ 1, 'two' ],
			[ '1', 'two' ],
		],
	] )( 'normalizes string list %p to %p', ( value, expected ) => {
		expect( normalizeStringList( value ) ).toEqual( expected );
	} );

	it.each( [
		[ '10,20', [ 10, 20 ] ],
		[
			[ '10', 20, 0, 'invalid', 10 ],
			[ 10, 20 ],
		],
		[ '', [] ],
	] )( 'normalizes ID list %p to %p', ( value, expected ) => {
		expect( normalizeIntegerList( value ) ).toEqual( expected );
	} );

	it( 'migrates a legacy FAQ attribute object with explicit defaults', () => {
		const legacy = {
			category: 'general,students',
			tag: 'important',
			id: '10,20',
			hide_title: 1,
		};

		expect( hasLegacyFaqAttributes( legacy ) ).toBe( true );
		expect( migrateFaqAttributes( legacy ) ).toMatchObject( {
			category: [ 'general', 'students' ],
			tag: [ 'important' ],
			id: [ 10, 20 ],
			hide_title: true,
			hide_accordion: false,
			masonry: false,
			search: false,
			style: 'light',
			sort: 'title',
			order: 'ASC',
			hstart: 2,
		} );
	} );

	it( 'detects raw legacy values after the current schema applied defaults', () => {
		const deprecation = faqDeprecations[ 0 ];

		expect(
			deprecation.isEligible( { category: [], id: [] }, [], {
				blockNode: {
					attrs: { category: 'general', id: '10,20' },
				},
			} )
		).toBe( true );
	} );
} );
