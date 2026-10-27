/* eslint-disable no-console */

const fs = require( 'fs' );
const os = require( 'os' );
const path = require( 'path' );
const { execFileSync } = require( 'child_process' );
const { createHash } = require( 'crypto' );

const root = path.resolve( __dirname, '../..' );
const temporary = fs.mkdtempSync(
	path.join( os.tmpdir(), 'rrze-answers-build-check-' )
);

/**
 * List files recursively using paths relative to the requested directory.
 *
 * @param {string} directory Directory to inspect.
 * @param {string} prefix Relative prefix used during recursion.
 * @return {string[]} Relative file paths.
 */
function filesIn( directory, prefix = '' ) {
	return fs
		.readdirSync( directory, { withFileTypes: true } )
		.flatMap( ( entry ) => {
			const relative = path.join( prefix, entry.name );
			return entry.isDirectory()
				? filesIn( path.join( directory, entry.name ), relative )
				: [ relative ];
		} );
}

/**
 * Hash a file without modifying it, returning null for a missing artifact.
 *
 * @param {string} filename File to inspect.
 * @return {string|null} SHA-256 digest or null.
 */
function digest( filename ) {
	return fs.existsSync( filename )
		? createHash( 'sha256' )
				.update( fs.readFileSync( filename ) )
				.digest( 'hex' )
		: null;
}

const trackedFiles = execFileSync( 'git', [ 'ls-files', '-z' ], {
	cwd: root,
	encoding: 'utf8',
} )
	.split( '\0' )
	.filter( Boolean );

for ( const relative of trackedFiles ) {
	const source = path.join( root, relative );
	if ( ! fs.existsSync( source ) ) {
		continue;
	}
	const destination = path.join( temporary, relative );
	fs.mkdirSync( path.dirname( destination ), { recursive: true } );
	fs.copyFileSync( source, destination );
}
fs.symlinkSync(
	path.join( root, 'node_modules' ),
	path.join( temporary, 'node_modules' ),
	'dir'
);

try {
	const output = execFileSync( 'npm', [ 'run', 'build' ], {
		cwd: temporary,
		encoding: 'utf8',
		maxBuffer: 10 * 1024 * 1024,
		stdio: [ 'ignore', 'pipe', 'pipe' ],
	} );
	fs.writeFileSync( path.join( temporary, 'build.stdout.log' ), output );
} catch ( error ) {
	fs.writeFileSync(
		path.join( temporary, 'build.stdout.log' ),
		error.stdout || ''
	);
	fs.writeFileSync(
		path.join( temporary, 'build.stderr.log' ),
		error.stderr || ''
	);
	console.error( `Build failed; diagnostic copy retained: ${ temporary }` );
	process.exit( 1 );
}

const committedBuild = path.join( root, 'build' );
const freshBuild = path.join( temporary, 'build' );
const artifacts = [
	...new Set( [ ...filesIn( committedBuild ), ...filesIn( freshBuild ) ] ),
].sort();
const differences = artifacts.flatMap( ( relative ) => {
	const committed = digest( path.join( committedBuild, relative ) );
	const rebuilt = digest( path.join( freshBuild, relative ) );
	return committed === rebuilt
		? []
		: [ { file: relative, committed, rebuilt } ];
} );
const report = { temporary, artifactCount: artifacts.length, differences };
fs.writeFileSync(
	path.join( temporary, 'comparison.json' ),
	JSON.stringify( report, null, 2 ) + '\n'
);
console.log( JSON.stringify( report, null, 2 ) );
process.exitCode = differences.length ? 1 : 0;
