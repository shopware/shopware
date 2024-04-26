import { defineConfig, loadEnv, normalizePath } from 'vite';
import { createHtmlPlugin } from 'vite-plugin-html';
import svgLoader from 'vite-svg-loader'
import vue from '@vitejs/plugin-vue';
import * as path from "path";
import * as fs from "fs";
import { contentType } from "mime-types";

console.warn('╔════════════════════════════════════════════════╗');
console.warn('║               EXPERIMENTAL VITE BUILD          ║');
console.warn('╚════════════════════════════════════════════════╝');

process.env = { ...process.env, ...loadEnv('', process.cwd()) };
process.env.PROJECT_ROOT = process.env.PROJECT_ROOT || path.join(__dirname, '/../../../../../');
if (!process.env.APP_URL) {
    throw new Error('APP_URL is not defined');
}

const isTwigFile = /\.twig$/;
const isTwigRawFile = /\.twig\?raw$/;
const isHTMLFile = /\.html$/;
const isHTMLRawFile = /\.html\?raw$/;
const flagsPath = path.join(process.env.PROJECT_ROOT, 'var', 'config_js_features.json');
let featureFlags = {};
if (fs.existsSync(flagsPath)) {
    featureFlags = JSON.parse(fs.readFileSync(flagsPath, 'utf-8'));
}

// eslint-disable-next-line
export default defineConfig({
    server: {
        proxy: {
            '/api': {
                target: process.env.APP_URL,
                changeOrigin: true,
                secure: false,
            },
        },
    },
    plugins: [
        vue({
            template: {
                compilerOptions: {
                    compatConfig: {
                        MODE: 2
                    }
                }
            }
        }),

        svgLoader(),

        createHtmlPlugin({
            minify: false,
            /**
             * After writing entry here, you will not need to add script tags in `index.html`,
             * the original tags need to be deleted
             * @default src/main.ts
             */
            entry: 'src/index.vite.ts',

            /**
             * Data that needs to be injected into the index.html ejs template
             */
            inject: {
                data: {
                    featureFlags: JSON.stringify(featureFlags),
                },
            },

        }),

        // Twig transformation
        {
            name: 'twig transform',

            async transform(fileContent, id) {
                if (id.endsWith('src/Administration/Resources/app/administration/index.html')) {
                    return;
                }

                if (
                    !(
                        isTwigFile.test(id) ||
                        isHTMLFile.test(id) ||
                        isTwigRawFile.test(id) ||
                        isHTMLRawFile.test(id)
                    )
                ) {
                    return;
                }


                fileContent = fileContent.replace(/<!--[\s\S]*?-->/gm, '');
                fileContent = fileContent.replace(/\n/g, '');
                fileContent = fileContent.replace(/"/g, '\\"');
                fileContent = fileContent.replace(/\$/g, '\\$');
                // replace all duplicated spaces
                fileContent = fileContent.replace(/ {2,}/g, ' ');

                const code = `export default \"${fileContent}\"`;

                // eslint-disable-next-line consistent-return
                return {
                    code,
                    ast: {
                        type: 'Program',
                        start: 0,
                        end: code.length,
                        body: [
                            {
                                type: 'ExportDefaultDeclaration',
                                start: 0,
                                end: code.length,
                                declaration: {
                                    type: 'Literal',
                                    start: 15,
                                    end: code.length,
                                    value: fileContent,
                                    raw: `"${fileContent}"`,
                                },
                            },
                        ],
                        sourceType: 'module',
                    },
                    map: null,
                };
            },
        },

        // Serve multiple static assets
        {
            name: 'serve-multiple-static',

            configureServer(server) {
                const staticMappings = [
                    {
                        directory: normalizePath(path.resolve(__dirname, 'static')),
                        publicPath: '/static',
                    },
                    {
                        directory: normalizePath(path.resolve(__dirname, 'static')),
                        publicPath: '/administration/static',
                    },
                    {
                        directory: normalizePath(path.resolve(__dirname, 'static')),
                        publicPath: '/bundles/administration/static',
                    },
                    // TODO: add plugin entries from Webpack here
                ]

                server.middlewares.use((req, res, next) => {
                    const originalUrl = req.originalUrl;

                    if (!originalUrl) {
                        return next();
                    }

                    // Check if the URL matches any of the static mappings and use the first match
                    const match = staticMappings.find((mapping) => {
                        if (originalUrl.startsWith(mapping.publicPath)) {
                            return true;
                        }
                    });

                    if (!match) {
                        return next();
                    }

                    // When URL starts with the public path, we need to serve the file from the directory
                    const filePath = path.join(match.directory, originalUrl.replace(match.publicPath, ''));
                    const stats = fs.statSync(filePath, { throwIfNoEntry: false });

                    // Check if the file exists
                    if (!stats || !stats.isFile()) {
                        res.writeHead(404);
                        res.end("Not found");
                        console.error(`File not found: ${filePath}`);
                        return;
                    }

                    // Set the content type based on the file extension
                    const type = contentType(path.basename(filePath));

                    // Write correct headers and pipe the file to the response
                    res.writeHead(200, {
                        'Content-Length': stats.size,
                        'Content-Type': type || undefined,
                    });

                    const stream = fs.createReadStream(filePath);
                    stream.pipe(res);

                    return;
                });
            }
        },
    ],

    resolve: {
        alias: [
            {
                find: /vue$/,
                replacement: '@vue/compat/dist/vue.esm-bundler.js',
            },
            {
                find: /^src\//,
                replacement: '/src/',
            },
            {
                // this is required for the SCSS modules
                find: /^~scss\/(.*)/,
                replacement: '/src/app/assets/scss/$1.scss',
            },
            {
                // this is required for the SCSS modules
                find: /^~(.*)$/,
                replacement: '$1',
            },
        ],
    },

    define: {
        global: {},
    },

    optimizeDeps: {
        include: [
            'vue-router',
            'vuex',
            'vue-i18n',
            'flatpickr',
            'flatpickr/**/*',
            'date-fns-tz'
        ],
        // This avoids full-page reload but the browser can't process more requests in parallel
        holdUntilCrawlEnd: true,
        esbuildOptions: {
            // Node.js global to browser globalThis
            define: {
                global: 'globalThis',
            },
        },
    },

    build: {
        // generate .vite/manifest.json in outDir
        manifest: true,
        rollupOptions: {
            // overwrite default .html entry
            input: 'src/index.vite.ts',
            output: {
                entryFileNames: `assets/[name].js`,
            }
        }
    }
});
