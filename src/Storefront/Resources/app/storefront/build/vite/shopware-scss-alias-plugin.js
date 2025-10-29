/**
 * @sw-package framework
 *
 * Vite plugin that transforms webpack-style ~ imports in SCSS files
 * Converts ~vendor/... to relative paths before Sass compilation
 */
import path from 'node:path';

export default function shopwareScssAliasPlugin(options = {}) {
    const { storefrontPath } = options;

    return {
        name: 'shopware-scss-alias',
        enforce: 'pre', // Run before other plugins

        transform(code, id) {
            // Only process SCSS/SASS files
            if (!id.endsWith('.scss') && !id.endsWith('.sass')) {
                return null;
            }

            // Replace ~vendor/ with absolute path to vendor directory
            const vendorPath = path.resolve(storefrontPath, 'vendor');
            const srcPath = path.resolve(storefrontPath, 'src');

            let transformedCode = code;

            // Replace ~vendor/ imports with absolute paths
            transformedCode = transformedCode.replace(
                /@import\s+['"]~vendor\/(.*?)['"];?/g,
                (match, importPath) => {
                    const absolutePath = path.join(vendorPath, importPath);
                    return `@import '${absolutePath}';`;
                }
            );

            // Replace ~src/ imports with absolute paths
            transformedCode = transformedCode.replace(
                /@import\s+['"]~src\/(.*?)['"];?/g,
                (match, importPath) => {
                    const absolutePath = path.join(srcPath, importPath);
                    return `@import '${absolutePath}';`;
                }
            );

            // Only return if we actually transformed something
            if (transformedCode !== code) {
                return {
                    code: transformedCode,
                    map: null,
                };
            }

            return null;
        },
    };
}

