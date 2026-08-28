import { useSelect } from '@wordpress/data';
import { useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const POSTS_PER_PAGE = 100;

function useEntityRecords( kind, name, query ) {
	return useSelect(
		( select ) => {
			const core = select( 'core' );
			const resolverArgs = [ kind, name, query ];
			const records = core.getEntityRecords( ...resolverArgs );

			return {
				records: records || [],
				isLoading:
					records === null ||
					records === undefined ||
					core.isResolving( 'getEntityRecords', resolverArgs ),
			};
		},
		[ kind, name, query ]
	);
}

function postToOption( post ) {
	return {
		label:
			post.title?.rendered ||
			__( 'No title', 'rrze-answers' ) + ` (#${ post.id })`,
		value: post.id,
	};
}

function termToOption( term, valueField ) {
	return {
		label: term.name,
		value: term[ valueField ],
	};
}

/**
 * Build hierarchical options without mutating records owned by core-data.
 *
 * @param {Array<Object>} terms      Taxonomy records from core-data.
 * @param {string}        valueField Record field used as the option value.
 * @return {Array<Object>} Flattened select options.
 */
export function buildHierarchicalTermOptions( terms, valueField = 'slug' ) {
	const nodes = new Map(
		terms.map( ( term ) => [
			term.id,
			{
				term,
				children: [],
			},
		] )
	);
	const roots = [];

	for ( const node of nodes.values() ) {
		const parent = node.term.parent
			? nodes.get( node.term.parent )
			: undefined;
		if ( parent ) {
			parent.children.push( node );
		} else {
			roots.push( node );
		}
	}

	const flatten = ( list, depth = 0 ) => {
		return [ ...list ]
			.sort( ( first, second ) =>
				first.term.name.localeCompare( second.term.name, undefined, {
					sensitivity: 'base',
				} )
			)
			.flatMap( ( node ) => [
				{
					...termToOption( node.term, valueField ),
					label: `${ '—'.repeat( depth ) } ${
						node.term.name
					}`.trim(),
				},
				...flatten( node.children, depth + 1 ),
			] );
	};

	return flatten( roots );
}

export function usePostOptions( postType, firstLabel, firstValue = 0 ) {
	const query = useMemo(
		() => ( {
			per_page: POSTS_PER_PAGE,
			orderby: 'title',
			order: 'asc',
			status: 'publish',
			_fields: 'id,title',
		} ),
		[]
	);
	const { records, isLoading } = useEntityRecords(
		'postType',
		postType,
		query
	);
	const options = useMemo(
		() => [
			{ label: firstLabel, value: firstValue },
			...records.map( postToOption ),
		],
		[ firstLabel, firstValue, records ]
	);

	return { records, options, isLoading };
}

export function useTermOptions(
	taxonomy,
	firstLabel,
	firstValue = '',
	{ hierarchical = false, valueField = 'slug' } = {}
) {
	const query = useMemo(
		() => ( {
			per_page: POSTS_PER_PAGE,
			hide_empty: false,
			orderby: 'name',
			order: 'asc',
			_fields: `id,name,${ valueField }${
				hierarchical ? ',parent' : ''
			}`,
		} ),
		[ hierarchical, valueField ]
	);
	const { records, isLoading } = useEntityRecords(
		'taxonomy',
		taxonomy,
		query
	);
	const options = useMemo( () => {
		const entityOptions = hierarchical
			? buildHierarchicalTermOptions( records, valueField )
			: records.map( ( term ) => termToOption( term, valueField ) );

		return [ { label: firstLabel, value: firstValue }, ...entityOptions ];
	}, [ firstLabel, firstValue, hierarchical, records, valueField ] );

	return { records, options, isLoading };
}
