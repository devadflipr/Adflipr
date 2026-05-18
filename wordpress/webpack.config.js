/**
 * Extends @wordpress/scripts webpack config with content-hashed filenames
 * and build/asset-manifest.json for PHP asset discovery.
 */
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const MiniCssExtractPlugin = require( 'mini-css-extract-plugin' );
const AdfliprBuildManifestPlugin = require( './scripts/adflipr-build-manifest-plugin' );

const HASH = '[contenthash:8]';

const plugins = defaultConfig.plugins.map( ( plugin ) => {
	if ( plugin instanceof MiniCssExtractPlugin ) {
		return new MiniCssExtractPlugin( {
			filename: `[name].${ HASH }.css`,
			chunkFilename: `[name].${ HASH }.css`,
		} );
	}
	return plugin;
} );

plugins.push( new AdfliprBuildManifestPlugin() );

module.exports = {
	...defaultConfig,
	output: {
		...defaultConfig.output,
		filename: `[name].${ HASH }.js`,
		chunkFilename: `[name].${ HASH }.js`,
	},
	plugins,
};
