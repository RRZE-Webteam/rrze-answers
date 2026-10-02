const defaults = require( '@wordpress/scripts/config/jest-unit.config' );

module.exports = {
	...defaults,
	// The block parser's CJS build also imports these ESM-only dependencies.
	transformIgnorePatterns: [
		'/node_modules/(?!marked/|uuid/|@wordpress/blocks/node_modules/uuid/)',
	],
};
