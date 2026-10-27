import fs from 'fs';
import path from 'path';
import vm from 'vm';
import { execFileSync } from 'child_process';
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
import {
	normalizeIntegerList,
	normalizeStringList,
	normalizeBoolean,
} from '../../src/editor/migrations/legacy-attributes';
import {
	create,
	registerFormatType,
	toHTMLString,
	unregisterFormatType,
} from '@wordpress/rich-text';

const root = path.resolve( __dirname, '../..' );
const mainReference =
	process.env.RRZE_ROLLOUT_MAIN_REF ||
	'3b458c812ba61c1f9b8beac22022cfd7b159ba58';
const minimumCoreAsset = process.env.RRZE_ROLLOUT_WP68_SSR;
const originalCategories = getCategories();
const deprecations = {
	faq: faqDeprecations,
	'faq-widget': faqWidgetDeprecations,
	glossary: glossaryDeprecations,
	placeholder: placeholderDeprecations,
	synonym: synonymDeprecations,
};

beforeAll( () =>
	setCategories( [ ...originalCategories, { slug: 'rrze', title: 'RRZE' } ] )
);
afterAll( () => setCategories( originalCategories ) );

/**
 * Read the exact legacy registration from the release reference under review.
 *
 * @param {string} slug Block directory name.
 * @return {Object} Legacy metadata from Git.
 */
function legacyMetadata( slug ) {
	return JSON.parse(
		execFileSync(
			'git',
			[ 'show', `${ mainReference }:blocks/${ slug }/block.json` ],
			{
				cwd: root,
				encoding: 'utf8',
			}
		)
	);
}

/**
 * Open and save a block under the candidate, then reopen under main's schema.
 *
 * @param {string} slug Block directory name.
 * @param {Object} attributes Main-compatible stored attributes.
 * @return {Object} Parsed legacy and candidate blocks plus serialized content.
 */
function roundTripThroughRollback( slug, attributes ) {
	const current = JSON.parse(
		fs.readFileSync(
			path.join( root, 'src/blocks', slug, 'block.json' ),
			'utf8'
		)
	);
	const legacy = legacyMetadata( slug );
	const content = `<!-- wp:${ legacy.name } ${ JSON.stringify(
		attributes
	) } /-->`;
	let candidate;
	let saved;
	registerBlockType( current.name, {
		...current,
		save: () => null,
		deprecated: deprecations[ slug ],
	} );
	try {
		[ candidate ] = parse( content );
		saved = serialize( [ candidate ] );
	} finally {
		unregisterBlockType( current.name );
	}
	registerBlockType( legacy.name, { ...legacy, save: () => null } );
	try {
		return { candidate, saved, reopened: parse( saved )[ 0 ] };
	} finally {
		unregisterBlockType( legacy.name );
	}
}

describe( 'release criterion: rollback after editor saves', () => {
	it.each( [ 'faq', 'glossary', 'synonym', 'placeholder' ] )(
		'%s retains the selected IDs on main',
		( slug ) => {
			const { candidate, reopened } = roundTripThroughRollback( slug, {
				id: '12,34',
			} );
			expect( candidate.attributes.id ).toEqual( [ 12, 34 ] );
			expect( normalizeIntegerList( reopened.attributes.id ) ).toEqual( [
				12, 34,
			] );
		}
	);
	it.each( [ 'faq', 'glossary', 'synonym' ] )(
		'%s retains taxonomy restrictions on main',
		( slug ) => {
			const { reopened } = roundTripThroughRollback( slug, {
				category: 'one,two',
				tag: 'selected',
			} );
			expect(
				normalizeStringList( reopened.attributes.category )
			).toEqual( [ 'one', 'two' ] );
			expect( normalizeStringList( reopened.attributes.tag ) ).toEqual( [
				'selected',
			] );
		}
	);
	it( 'the FAQ widget retains its hidden title on main', () => {
		const { candidate, reopened } = roundTripThroughRollback(
			'faq-widget',
			{
				id: 12,
				hide_title: 1,
			}
		);
		expect( candidate.attributes.hide_title ).toBe( true );
		expect( normalizeBoolean( reopened.attributes.hide_title ) ).toBe(
			true
		);
	} );
} );

/**
 * Provide inert editor controls while retaining actual compiled entry points.
 *
 * The official core asset is evaluated without replacing its exported API.
 * Controls and hooks are only shallow-rendered; this is not a browser test.
 * Registration mocks collect definitions, memo hooks evaluate synchronously,
 * and data selectors expose an empty, resolved collection. The legacy withSelect
 * mock preserves the wrapped component, while translation and URL mocks pass
 * their inputs through. API and UI stand-ins have no side effects or network
 * activity. JSX records element types and props without mounting children.
 *
 * @return {Object} VM context and captured block/format registrations.
 */
function createEditorContext() {
	const registrations = {};
	const formats = {};
	/**
	 * Represent an unused UI component or service without producing effects.
	 *
	 * @return {null} Empty shallow-render result.
	 */
	const placeholder = () => null;
	const element = {
		useMemo: ( callback ) => callback(),
		useCallback: ( callback ) => callback,
	};
	const wp = {
		blocks: {
			registerBlockType: ( metadata, settings ) => {
				registrations[
					typeof metadata === 'string' ? metadata : metadata.name
				] = { metadata, ...settings };
			},
		},
		blockEditor: new Proxy(
			{ useBlockProps: ( properties ) => properties || {} },
			{ get: ( target, key ) => target[ key ] || placeholder }
		),
		components: new Proxy( {}, { get: () => placeholder } ),
		data: {
			useSelect: ( callback ) =>
				callback( () => ( {
					getEntityRecords: () => [],
					isResolving: () => false,
					getCurrentPostId: () => 0,
				} ) ),
			// Core 6.8 wraps its component with this HOC at module evaluation.
			withSelect: () => ( component ) => component,
		},
		element,
		i18n: { __: ( value ) => value, sprintf: ( value ) => value },
		apiFetch: placeholder,
		url: { addQueryArgs: ( value ) => value },
		compose: new Proxy( {}, { get: () => placeholder } ),
		richText: {
			registerFormatType: ( name, settings ) => {
				formats[ name ] = settings;
			},
		},
	};
	/**
	 * Record a JSX element so tests can inspect its component type and props.
	 *
	 * @param {*} type Component, tag or unresolved imported export.
	 * @param {Object} props Element properties, including nested children.
	 * @return {Object} Shallow element descriptor without executing the component.
	 */
	const jsx = ( type, props ) => ( { type, props } );
	const window = {
		wp,
		React: { createElement: jsx },
		ReactJSXRuntime: { jsx, jsxs: jsx, Fragment: 'fragment' },
	};
	return {
		context: vm.createContext( {
			window,
			console,
			setTimeout,
			clearTimeout,
		} ),
		registrations,
		formats,
	};
}

/**
 * Execute the exact committed block assets and capture their registrations.
 *
 * @param {Object} runtime Isolated editor context.
 * @return {void}
 */
function loadCommittedBundles( runtime ) {
	for ( const slug of Object.keys( deprecations ) ) {
		const filename = path.join( root, 'build/blocks', slug, 'index.js' );
		vm.runInContext( fs.readFileSync( filename, 'utf8' ), runtime.context, {
			filename,
		} );
	}
}

/**
 * Find the server-preview element in a shallow element tree.
 *
 * @param {*} node Element or child collection.
 * @param {string} name Registered block name.
 * @return {Object|undefined} Matching preview element.
 */
function previewElement( node, name ) {
	if ( Array.isArray( node ) ) {
		return node
			.map( ( child ) => previewElement( child, name ) )
			.find( Boolean );
	}
	if ( ! node || typeof node !== 'object' ) {
		return undefined;
	}
	return node.props?.block === name
		? node
		: previewElement( node.props?.children, name );
}

( minimumCoreAsset ? describe : describe.skip )(
	'release criterion: committed editor bundles on WordPress 6.8',
	() => {
		let runtime;
		beforeAll( () => {
			runtime = createEditorContext();
			vm.runInContext(
				fs.readFileSync( minimumCoreAsset, 'utf8' ),
				runtime.context,
				{ filename: minimumCoreAsset }
			);
			loadCommittedBundles( runtime );
		} );
		it( 'loads the official minimum-core default export', () => {
			expect( typeof runtime.context.window.wp.serverSideRender ).toBe(
				'function'
			);
		} );
		it.each( Object.keys( deprecations ) )(
			'%s receives a callable preview component',
			( slug ) => {
				const name = `rrze-answers/${ slug }`;
				const registration = runtime.registrations[ name ];
				const attributes = Object.fromEntries(
					Object.entries( registration.metadata.attributes ).map(
						( [ key, value ] ) => [ key, value.default ]
					)
				);
				const tree = registration.edit( {
					attributes,
					setAttributes: () => {},
				} );
				const preview = previewElement( tree, name );
				expect( preview ).toBeDefined();
				expect( typeof preview.type ).toBe( 'function' );
			}
		);
	}
);

describe( 'existing inline format round trips', () => {
	const cases = [
		[
			'synonym',
			'<abbr class="rrze-syn" title="Regional Computing Centre" lang="en" data-pron="rrze">RRZE</abbr>',
			{
				title: 'Regional Computing Centre',
				lang: 'en',
				'data-pron': 'rrze',
			},
		],
		[
			'placeholder',
			'<placeholder class="rrze-placeholder" data-placeholder-id="2301" data-placeholder-title="Office" title="Office: &lt;strong&gt;09:00&lt;/strong&gt;">[Placeholder: Office]</placeholder>',
			{
				'data-placeholder-id': '2301',
				'data-placeholder-title': 'Office',
				title: 'Office: <strong>09:00</strong>',
			},
		],
	];
	it.each( cases )(
		'%s retains the format contract and stored attributes from main',
		( slug, html, expectedAttributes ) => {
			const current = createEditorContext();
			loadCommittedBundles( current );
			const legacy = createEditorContext();
			const legacyBundle = execFileSync(
				'git',
				[ 'show', `${ mainReference }:build/${ slug }.js` ],
				{ cwd: root, encoding: 'utf8' }
			);
			vm.runInContext( legacyBundle, legacy.context );
			const name = `rrze/${ slug }`;
			const registration = current.formats[ name ];
			for ( const key of [ 'tagName', 'className', 'attributes' ] ) {
				expect( registration[ key ] ).toEqual(
					legacy.formats[ name ][ key ]
				);
			}
			registerFormatType( name, registration );
			try {
				const value = create( { html } );
				expect(
					value.formats.some( ( formats ) =>
						formats?.some( ( format ) => format.type === name )
					)
				).toBe( true );
				const saved = toHTMLString( { value } );
				const container = document.createElement( 'div' );
				container.innerHTML = saved;
				const element = container.querySelector( registration.tagName );
				expect( element ).not.toBeNull();
				for ( const [ attribute, expected ] of Object.entries(
					expectedAttributes
				) ) {
					expect( element.getAttribute( attribute ) ).toBe(
						expected
					);
				}
				expect(
					toHTMLString( { value: create( { html: saved } ) } )
				).toBe( saved );
			} finally {
				unregisterFormatType( name );
			}
		}
	);
} );
