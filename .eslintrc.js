/**
 * ESLint configuration extending wp-scripts defaults.
 *
 * Adds `.jsx` to the import resolver so that bare imports like
 * `./analysis-results` resolve correctly to `analysis-results.jsx`.
 */
module.exports = {
	extends: [ 'plugin:@wordpress/eslint-plugin/recommended' ],
	settings: {
		'import/extensions': [ '.js', '.jsx' ],
		'import/resolver': {
			node: {
				extensions: [ '.js', '.jsx' ],
			},
		},
	},
};
