const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

const defaultEntry = defaultConfig.entry;

/**
 * Keep non-block application entry points in the same compilation as the
 * auto-discovered block entries. This gives start/build one owner for the
 * build directory and preserves WordPress dependency extraction.
 */
module.exports = {
	...defaultConfig,
	entry: async () => ( {
		...( typeof defaultEntry === 'function'
			? await defaultEntry()
			: defaultEntry ),
		'rrze-answers-accordion': path.resolve(
			process.cwd(),
			'src/js/rrze-answers-accordion.js'
		),
		'rrze-answers-import-ui': path.resolve(
			process.cwd(),
			'src/js/rrze-answers-import-ui.js'
		),
		'rrze-answers-search': path.resolve(
			process.cwd(),
			'src/js/rrze-answers-search.js'
		),
		'rrze-answers-guided-tour': path.resolve(
			process.cwd(),
			'src/js/rrze-answers-guided-tour.js'
		),
	} ),
	output: {
		...defaultConfig.output,
		// Sass owns build/css. Preserve it when the development watcher starts.
		clean:
			process.env.NODE_ENV === 'production'
				? defaultConfig.output.clean
				: { keep: /^(?:fonts|images|css)\// },
	},
};
