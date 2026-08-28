const fs = require( 'fs' );
const path = require( 'path' );

const manifestPath = path.resolve( process.cwd(), 'build/blocks-manifest.php' );

if ( ! fs.existsSync( manifestPath ) ) {
	process.exit( 0 );
}

const manifest = fs.readFileSync( manifestPath, 'utf8' );
const normalizedManifest = manifest.replace( /[\t ]+$/gm, '' );

if ( normalizedManifest !== manifest ) {
	fs.writeFileSync( manifestPath, normalizedManifest );
}
