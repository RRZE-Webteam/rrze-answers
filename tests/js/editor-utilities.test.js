jest.mock(
	'@wordpress/data',
	() => ( {
		useSelect: jest.fn(),
	} ),
	{ virtual: true }
);

import { buildHierarchicalTermOptions } from '../../src/editor/hooks/use-entity-options';
import { parseLegacyMultiValue } from '../../src/editor/hooks/use-legacy-multi-select';

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

describe( 'parseLegacyMultiValue', () => {
	it.each( [
		[ 'one,two', [ 'one', 'two' ] ],
		[ ' one, two, ', [ 'one', 'two' ] ],
		[ 42, [ '42' ] ],
		[ '', [ '' ] ],
		[ undefined, [ '' ] ],
		[ [], [ '' ] ],
		[
			[ 1, 'two' ],
			[ '1', 'two' ],
		],
	] )( 'normalizes %p to %p', ( value, expected ) => {
		expect( parseLegacyMultiValue( value ) ).toEqual( expected );
	} );
} );
