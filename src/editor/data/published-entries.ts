import apiFetch from '@wordpress/api-fetch';

/**
 * Follow REST pagination links, including collections with exactly 100 items.
 *
 * @param {string} restBase REST collection for the requested entry type.
 * @param {string} fields   Comma-separated response fields.
 * @return {Promise<Array>} All published entries in title order.
 */
export function fetchPublishedEntries< T >(
	restBase: 'placeholder' | 'synonym',
	fields: string
): Promise< T[] > {
	return apiFetch< T[] >( {
		path: `/wp/v2/${ restBase }?status=publish&per_page=-1&orderby=title&order=asc&_fields=${ fields }`,
	} );
}
