const fs = require('fs');
const path = require('path');

const fileParser = require(`${__dirname}/lib/file-parser`); // eslint-disable-line import/no-dynamic-require
const process = require('process');

function getPathFromRoot(directory) {
    const projectRoot = process.env.PROJECT_ROOT;
    return path.join(projectRoot, directory);
}

const configFileName = 'component-library.conf.js';
module.exports = {
    env: {
        NODE_ENV: 'development'
    },
    router: {
        mode: 'history',
        scrollBehavior() {
            return { x: 0, y: 0 };
        }
    },
    modules: [
        '~/modules/parser/index'
    ],
    head: {
        titleTemplate: '%s - Shopware Component library',
        meta: [
            { charset: 'utf-8' },
            { name: 'viewport', content: 'width=device-width, initial-scale=1' },
            { hid: 'description', name: 'description', content: 'Sample description' }
        ]
    },
    css: [
        '~/static/administration/static/css/app.css',
        '~/assets/css/main.scss'
    ],
    plugins: [
        '~/plugins/vue-prism',
        { src: '~/plugins/shopware', ssr: false }
    ],

    build: {
        extend(config, { isDev }) {
            if (!isDev) {
                config.output.publicPath = '/_nuxt/';
            }
            config.resolve.alias.src = path.resolve(__dirname, '..', '../src/');
            config.resolve.alias.scss = path.resolve(__dirname, '..', '../src/app/assets/scss');
            config.resolve.alias.vue = `${__dirname}/node_modules/vue/dist/vue.common`;
            config.resolve.extensions.push('.twig', '.scss');

            const urlLoader = config.module.rules.find((loader) => {
                return loader.use && loader.use[0].loader === 'url-loader';
            });

            urlLoader.test = /\.(png|jpe?g|gif)$/;

            config.module.rules.push({
                test: /\.(html|twig)$/,
                loader: 'html-loader'
            }, {
                test: /\.svg$/,
                loader: 'svg-inline-loader',
                options: {
                    removeSVGTagAttrs: false
                }
            });
        },
        babel: {
            compact: false,
            plugins: [
                '@babel/plugin-transform-modules-commonjs',
                '@babel/plugin-proposal-optional-chaining',
                '@babel/plugin-proposal-nullish-coalescing-operator'
            ]
        }
    },
    generate: {
        dir: getPathFromRoot('build/artifacts/component-library'),
        routes: async () => {
            let config;
            // Check if we're having a config file, otherwise set the config
            if (fs.existsSync(path.resolve(__dirname, configFileName))) {
                config = require(path.resolve(__dirname, configFileName)); // eslint-disable-line
            } else {
                console.warn(`No config file "${configFileName}" in project root found`);
                config = {};
                config.components = 'src/components/**/*.js';
                config.sassVariables = '';
            }

            return fileParser(config).then((files) => {
                return files.reduce((accumulator, file) => {
                    // skip meteor components
                    if (!file || !file.source || !file.source.path || file.source.path.includes('meteor')) {
                        return accumulator;
                    }

                    if (file.source.meta.hasOwnProperty('private') && file.source.meta.private === true) {
                        return accumulator;
                    }
                    accumulator.push(`/components/${file.source.name}`);
                    return accumulator;
                }, []);
            });
        }
    }
};
