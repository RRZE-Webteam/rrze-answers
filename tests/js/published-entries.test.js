import apiFetch from '@wordpress/api-fetch';
import { fetchPublishedEntries } from '../../src/editor/data/published-entries';

describe( 'published entry pagination', () => {
	it.each( [ 0, 100, 101, 200 ] )(
		'loads all %i entries without requesting a nonexistent page',
		async ( count ) => {
			const entries = Array.from( { length: count }, ( _, i ) => ( {
				id: i + 1,
			} ) );
			const fetch = jest.fn( async ( options ) => {
				const url = new URL(
					options.url || options.path,
					'https://example.org'
				);
				const page = Number( url.searchParams.get( 'page' ) || 1 );
				const perPage = Number( url.searchParams.get( 'per_page' ) );
				expect( perPage ).toBe( 100 );
				expect( url.searchParams.get( 'status' ) ).toBe( 'publish' );
				if ( page > Math.max( 1, Math.ceil( count / perPage ) ) ) {
					throw new Error( 'Requested a page past the end' );
				}
				const next = new URL( url );
				next.searchParams.set( 'page', page + 1 );
				return {
					json: async () =>
						entries.slice( ( page - 1 ) * perPage, page * perPage ),
					headers: {
						get: ( name ) =>
							name === 'link' && page * perPage < count
								? `<${ next.toString() }>; rel="next"`
								: null,
					},
				};
			} );
			apiFetch.setFetchHandler( fetch );

			await expect(
				fetchPublishedEntries( 'placeholder', 'id,title' )
			).resolves.toEqual( entries );
			expect( fetch ).toHaveBeenCalledTimes(
				Math.max( 1, Math.ceil( count / 100 ) )
			);
		}
	);

	it( 'rejects a failed later page instead of returning a partial collection', async () => {
		apiFetch.setFetchHandler(
			jest
				.fn()
				.mockResolvedValueOnce( {
					json: async () => [ { id: 1 } ],
					headers: {
						get: () =>
							'<https://example.org/wp/v2/synonym?page=2>; rel="next"',
					},
				} )
				.mockRejectedValueOnce( new Error( 'Unavailable' ) )
		);
		await expect(
			fetchPublishedEntries( 'synonym', 'id,title,synonym' )
		).rejects.toThrow( 'Unavailable' );
	} );
} );
