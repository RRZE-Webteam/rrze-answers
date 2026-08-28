import { useSelect } from '@wordpress/data';
import { useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const POSTS_PER_PAGE = 100;

export interface SelectOption {
	label: string;
	value: string;
}

interface EntityQuery {
	[ key: string ]: string | number | boolean;
}

interface PostRecord {
	id: number;
	title?: {
		rendered?: string;
	};
}

interface TermRecord {
	id: number;
	name: string;
	slug: string;
	parent?: number;
}

interface CoreDataSelectors {
	getEntityRecords: < T >(
		kind: string,
		name: string,
		query: EntityQuery
	) => T[] | null | undefined;
	isResolving: ( selectorName: string, args: unknown[] ) => boolean;
}

interface EntityRecordsResult< T > {
	records: T[];
	isLoading: boolean;
}

type TermValueField = 'id' | 'slug';

function useEntityRecords< T >(
	kind: string,
	name: string,
	query: EntityQuery
): EntityRecordsResult< T > {
	return useSelect(
		( select ) => {
			const selectStore = select as unknown as (
				storeName: string
			) => CoreDataSelectors;
			const core = selectStore( 'core' );
			const resolverArgs = [ kind, name, query ];
			const records = core.getEntityRecords< T >( kind, name, query );

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

function postToOption( post: PostRecord ): SelectOption {
	return {
		label:
			post.title?.rendered ||
			__( 'No title', 'rrze-answers' ) + ` (#${ post.id })`,
		value: String( post.id ),
	};
}

function termToOption(
	term: TermRecord,
	valueField: TermValueField
): SelectOption {
	return {
		label: term.name,
		value: String( term[ valueField ] ),
	};
}

/**
 * Build hierarchical options without mutating records owned by core-data.
 *
 * @param {Array<Object>} terms      Taxonomy records from core-data.
 * @param {string}        valueField Record field used as the option value.
 * @return {Array<Object>} Flattened select options.
 */
export function buildHierarchicalTermOptions(
	terms: TermRecord[],
	valueField: TermValueField = 'slug'
): SelectOption[] {
	interface TermNode {
		term: TermRecord;
		children: TermNode[];
	}

	const nodes = new Map< number, TermNode >(
		terms.map( ( term ) => [
			term.id,
			{
				term,
				children: [],
			},
		] )
	);
	const roots: TermNode[] = [];

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

	const flatten = ( list: TermNode[], depth = 0 ): SelectOption[] => {
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

export function usePostOptions(
	postType: string,
	firstLabel: string,
	firstValue: string | number = ''
): EntityRecordsResult< PostRecord > & { options: SelectOption[] } {
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
	const { records, isLoading } = useEntityRecords< PostRecord >(
		'postType',
		postType,
		query
	);
	const options = useMemo(
		() => [
			{ label: firstLabel, value: String( firstValue ) },
			...records.map( postToOption ),
		],
		[ firstLabel, firstValue, records ]
	);

	return { records, options, isLoading };
}

export function useTermOptions(
	taxonomy: string,
	firstLabel: string,
	firstValue: string | number = '',
	{
		hierarchical = false,
		valueField = 'slug',
	}: { hierarchical?: boolean; valueField?: TermValueField } = {}
): EntityRecordsResult< TermRecord > & { options: SelectOption[] } {
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
	const { records, isLoading } = useEntityRecords< TermRecord >(
		'taxonomy',
		taxonomy,
		query
	);
	const options = useMemo( () => {
		const entityOptions = hierarchical
			? buildHierarchicalTermOptions( records, valueField )
			: records.map( ( term ) => termToOption( term, valueField ) );

		return [
			{ label: firstLabel, value: String( firstValue ) },
			...entityOptions,
		];
	}, [ firstLabel, firstValue, hierarchical, records, valueField ] );

	return { records, options, isLoading };
}
