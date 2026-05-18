/**
 * Writes build/asset-manifest.json after each production build.
 * PHP reads this file to enqueue content-hashed JS/CSS without hardcoded names.
 */
const { readFileSync, writeFileSync } = require( 'fs' );
const { resolve } = require( 'path' );

const ENTRY_NAME = 'index';
const MANIFEST_FILE = 'asset-manifest.json';

/**
 * @param {import('webpack').Compilation} compilation
 * @returns {{ js: string, css: string[] } | null}
 */
function collectEntryAssets( compilation ) {
	const entry = compilation.entrypoints.get( ENTRY_NAME );
	if ( ! entry ) {
		return null;
	}

	const files = entry.getFiles();
	const jsFiles = files.filter( ( file ) => file.endsWith( '.js' ) );
	const cssFiles = files.filter( ( file ) => file.endsWith( '.css' ) );

	if ( jsFiles.length === 0 ) {
		return null;
	}

	const mainJs =
		jsFiles.find( ( file ) => file.startsWith( `${ ENTRY_NAME }.` ) ) ||
		jsFiles[ 0 ];

	return {
		js: mainJs.replace( /^\.\//, '' ),
		css: cssFiles.map( ( file ) => file.replace( /^\.\//, '' ) ),
	};
}

/**
 * @param {string} buildDir
 * @param {string} mainJs e.g. index.926ab366.js
 * @returns {{ dependencies: string[], version: string }}
 */
function readIndexAssetPhp( buildDir, mainJs ) {
	const assetPhpName = mainJs.replace( /\.js$/, '.asset.php' );
	const assetPhpPath = resolve( buildDir, assetPhpName );
	const content = readFileSync( assetPhpPath, 'utf8' );

	const versionMatch = content.match(
		/'version'\s*=>\s*'([a-f0-9]+)'/
	);
	const depsMatch = content.match(
		/'dependencies'\s*=>\s*array\(([\s\S]*?)\)/
	);

	const dependencies = [];
	if ( depsMatch ) {
		const depBlock = depsMatch[ 1 ];
		const depRegex = /'([^']+)'/g;
		let dep;
		while ( ( dep = depRegex.exec( depBlock ) ) ) {
			dependencies.push( dep[ 1 ] );
		}
	}

	return {
		dependencies,
		version: versionMatch ? versionMatch[ 1 ] : '',
	};
}

class AdfliprBuildManifestPlugin {
	apply( compiler ) {
		compiler.hooks.done.tap( 'AdfliprBuildManifestPlugin', ( stats ) => {
			if ( stats.hasErrors() ) {
				return;
			}

			const compilation = stats.compilation;
			const entryAssets = collectEntryAssets( compilation );
			if ( ! entryAssets ) {
				return;
			}

			const buildDir = compilation.outputOptions.path;
			const assetMeta = readIndexAssetPhp( buildDir, entryAssets.js );

			const hashFromFilename = entryAssets.js.match(
				/^index\.([a-f0-9]+)\.js$/
			);

			const manifest = {
				entry: ENTRY_NAME,
				js: entryAssets.js,
				css: entryAssets.css,
				dependencies: assetMeta.dependencies,
				version:
					assetMeta.version ||
					( hashFromFilename ? hashFromFilename[ 1 ] : '' ),
				builtAt: new Date().toISOString(),
			};

			writeFileSync(
				resolve( buildDir, MANIFEST_FILE ),
				JSON.stringify( manifest, null, 2 ) + '\n',
				'utf8'
			);
		} );
	}
}

module.exports = AdfliprBuildManifestPlugin;
