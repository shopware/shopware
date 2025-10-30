/**
 * @sw-package framework
 */
import { defineConfig } from 'vite';
import path from 'node:path';
import fs from 'node:fs';
import chalk from 'chalk';
import { fileURLToPath } from 'node:url';
import shopwareMultiCompilerPlugin from './build/vite/shopware-multi-compiler-plugin.js';
import shopwareBasePathPlugin from './build/vite/shopware-base-path-plugin.js';
import shopwareScssPlugin from './build/vite/shopware-scss-plugin.js';
import shopwareScssAliasPlugin from './build/vite/shopware-scss-alias-plugin.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

if (process.env.IPV4FIRST) {
    import('node:dns').then(dns => {
        dns.setDefaultResultOrder('ipv4first');
    });
}

const projectRootPath = process.env.PROJECT_ROOT
    ? path.resolve(process.env.PROJECT_ROOT)
    : path.resolve('../../../../..');

// Load plugin definitions
const pluginFile = path.resolve(projectRootPath, 'var/plugins.json');

if (!fs.existsSync(pluginFile)) {
    throw new Error(`The file ${pluginFile} could not be found. Try bin/console bundle:dump to create this file.`);
}

const pluginDefinition = JSON.parse(fs.readFileSync(pluginFile, 'utf8'));

const pluginEntries = Object.entries(pluginDefinition)
    .filter(([, definition]) => {
        return definition.technicalName !== 'storefront' && 
               !!definition.storefront && 
               !!definition.storefront.entryFilePath && 
               !Object.prototype.hasOwnProperty.call(process.env, 'SKIP_' + definition.technicalName.toUpperCase().replace(/-/g, '_'));
    })
    .map(([name, definition]) => {
        // eslint-disable-next-line no-console
        console.log(chalk.bgGreenBright.black(`# Plugin "${name}": Injected successfully`));

        const technicalName = definition.technicalName || name.replace(/([a-z])([A-Z])/g, '$1-$2').toLowerCase();
        
        return {
            name,
            technicalName: technicalName,
            technicalFolderName: technicalName.replace(/(-)/g, '').toLowerCase(),
            basePath: path.resolve(projectRootPath, definition.basePath),
            path: path.resolve(projectRootPath, definition.basePath, definition.storefront.path),
            filePath: path.resolve(projectRootPath, definition.basePath, definition.storefront.entryFilePath),
            isTheme: definition.isTheme,
            views: definition.views || [],
        };
    });

export default defineConfig(({ mode, command }) => {
    const isDev = mode === 'development';
    const isProd = mode === 'production';
    const isServe = command === 'serve';
    
    // Control SCSS deprecation warnings via environment variable
    const silenceScssDeprecations = process.env.SILENCE_SCSS_DEPRECATIONS !== 'false';
    
    // Storefront dev server port (different from admin port 5173)
    const devServerPort = parseInt(process.env.STOREFRONT_VITE_PORT || '5175', 10);

    return {
        server: {
            port: devServerPort,
            strictPort: true,
            cors: {
                origin: '*', // Allow all origins for dev server
                methods: ['GET', 'POST', 'OPTIONS'],
                allowedHeaders: ['*'],
                credentials: true,
            },
            host: '0.0.0.0',
            hmr: {
                protocol: 'ws',
                host: 'localhost',
                port: devServerPort,
                clientPort: devServerPort,
            },
            fs: {
                // Allow serving files from the project root (needed for extensions in custom/apps)
                allow: [projectRootPath],
            },
            watch: {
                // Watch extension source files
                ignored: ['!**/custom/apps/*/Resources/app/storefront/src/**'],
            },
        },

        build: {
            outDir: 'dist',
            emptyOutDir: false, // Keep static assets in dist/assets
            sourcemap: isDev ? true : false,
            manifest: true,
            reportCompressedSize: false,  // Skip gzip calculation in dev for speed
            rollupOptions: {
                input: {
                    storefront: path.resolve(__dirname, 'src/main.js'),
                    // Add all extension entry points to the main build
                    ...Object.fromEntries(
                        pluginEntries.map(plugin => [plugin.technicalName, plugin.filePath])
                    ),
                },
                output: {
                    format: 'es', // Native ES modules
                    entryFileNames: 'storefront/[name].js',
                    chunkFileNames: 'storefront/[name].js',
                    assetFileNames: (assetInfo) => {
                        if (/\.(woff2?|ttf|eot|otf)$/.test(assetInfo.name)) {
                            return 'assets/font/[name][extname]';
                        }
                        if (/\.(jpe?g|png|gif|svg)$/.test(assetInfo.name)) {
                            return 'assets/img/[name][extname]';
                        }
                        if (/\.css$/.test(assetInfo.name)) {
                            return 'css/[name].css';
                        }
                        return 'assets/[name]-[hash][extname]';
                    },
                },
            },
            target: 'es2020',
            minify: isProd ? 'terser' : false,
            terserOptions: isProd ? {
                compress: true,
            } : undefined,
        },

        resolve: {
            alias: {
                src: path.resolve(__dirname, 'src'),
                assets: path.resolve(__dirname, 'assets'),
                vendor: path.resolve(__dirname, 'vendor'),
                // Handle webpack-style ~ prefix for SCSS imports
                '~vendor': path.resolve(__dirname, 'vendor'),
                '~src': path.resolve(__dirname, 'src'),
            },
            extensions: ['.ts', '.tsx', '.js', '.jsx', '.json', '.twig'],
        },

        plugins: [
            shopwareBasePathPlugin(),
            // Transform webpack-style ~ imports in SCSS
            ...(isServe ? [shopwareScssAliasPlugin({
                storefrontPath: __dirname,
            })] : []),
            // SCSS compilation for dev server mode
            ...(isServe ? [shopwareScssPlugin({
                projectRootPath,
                enabled: process.env.DISABLE_STOREFRONT_SCSS !== 'true',
            })] : []),
            // Only use multi-compiler for build mode, not dev server
            // In dev server mode, extensions are added as input entries above
            ...(!isServe ? [shopwareMultiCompilerPlugin({
                projectRootPath,
                pluginEntries,
                isDev,
            })] : []),
        ],

        css: {
            devSourcemap: true,
            preprocessorOptions: {
                scss: {
                    // Silence SCSS deprecation warnings (can be disabled with SILENCE_SCSS_DEPRECATIONS=false)
                    ...(silenceScssDeprecations && {
                        silenceDeprecations: [
                            'legacy-js-api',
                            'import',
                            'global-builtin',
                            'color-functions',
                            'slash-div',
                        ],
                    }),
                    // Suppress verbose output
                    quietDeps: true,
                    // Modern API configuration
                    api: 'modern-compiler',
                },
            },
        },

        optimizeDeps: {
            include: [
                'bootstrap',
                '@popperjs/core',
                'flatpickr',
                'hammerjs',
                'tiny-slider',
            ],
        },

        esbuild: {
            logOverride: { 'this-is-undefined-in-esm': 'silent' },
        },

        logLevel: 'info',
    };
});


