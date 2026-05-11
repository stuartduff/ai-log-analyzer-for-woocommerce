/**
 * Custom webpack configuration extending @wordpress/scripts defaults.
 *
 * Defines explicit entry points so both the analyze app and any future
 * JS-only additions are built from the same config.
 */
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: {
		analyze: './src/analyze/index.js',
	},
};
