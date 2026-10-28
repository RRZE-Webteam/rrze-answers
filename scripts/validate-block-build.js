/* eslint-disable no-console */

const fs = require( 'fs' );
const path = require( 'path' );

const pluginRoot = path.resolve( __dirname, '..' );
const buildRoot = path.join( pluginRoot, 'build' );
const blocksRoot = path.join( buildRoot, 'blocks' );
const manifestPath = path.join( buildRoot, 'blocks-manifest.php' );
const expectedBlocks = [
	'faq',
	'faq-widget',
	'glossary',
	'placeholder',
	'synonym',
];
const assetFields = [
	'editorScript',
	'editorStyle',
	'render',
	'script',
	'style',
	'viewScript',
	'viewScriptModule',
	'viewStyle',
];
const errors = [];

if ( ! fs.existsSync( manifestPath ) ) {
	errors.push( 'build/blocks-manifest.php is missing.' );
}

if ( fs.existsSync( manifestPath ) ) {
	const manifest = fs.readFileSync( manifestPath, 'utf8' );
	for ( const blockName of expectedBlocks ) {
		if ( ! manifest.includes( `'${ blockName }' => array(` ) ) {
			errors.push(
				`build/blocks-manifest.php does not contain ${ blockName }.`
			);
		}
	}
}

for ( const blockName of expectedBlocks ) {
	const blockDirectory = path.join( blocksRoot, blockName );
	const metadataPath = path.join( blockDirectory, 'block.json' );

	if ( ! fs.existsSync( metadataPath ) ) {
		errors.push( `build/blocks/${ blockName }/block.json is missing.` );
		continue;
	}

	const metadata = JSON.parse( fs.readFileSync( metadataPath, 'utf8' ) );
	for ( const field of assetFields ) {
		const values = Array.isArray( metadata[ field ] )
			? metadata[ field ]
			: [ metadata[ field ] ];

		for ( const value of values.filter( Boolean ) ) {
			if ( typeof value !== 'string' || ! value.startsWith( 'file:' ) ) {
				continue;
			}

			const assetPath = path.resolve(
				blockDirectory,
				value.slice( 'file:'.length )
			);
			if ( ! fs.existsSync( assetPath ) ) {
				errors.push(
					`${
						metadata.name
					} ${ field } references missing file ${ path.relative(
						pluginRoot,
						assetPath
					) }.`
				);
			}
		}
	}
}

if ( errors.length ) {
	console.error( errors.join( '\n' ) );
	process.exitCode = 1;
} else {
	console.log( `Validated ${ expectedBlocks.length } built blocks.` );
}
