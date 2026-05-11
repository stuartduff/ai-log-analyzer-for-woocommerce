const baseConfig = require( '@wordpress/scripts/config/jest-unit.config.js' );

module.exports = {
	...baseConfig,
	setupFilesAfterEnv: [ '<rootDir>/tests/js/jest-setup.js' ],
};
