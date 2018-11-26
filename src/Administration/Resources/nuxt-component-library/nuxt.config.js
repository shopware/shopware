const fs = require('fs');
const path = require('path');
const fileParser = require(__dirname + '/lib/file-parser');

function getPathFromRoot(directory) {
    return path.join(__dirname, '../../../../../../../', directory);
}

console.log();

const configFileName = 'component-library.conf.js';
let config;

module.exports = {
    env: {
        NODE_ENV: 'development'
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
        '~/assets/css/main.less'
    ],
    plugins: [
        '~/plugins/vue-prism',
        { src: '~/plugins/shopware', ssr: false }
    ],

    build: {
        extend(config,  { isDev }) {
            if (!isDev) {
                config.output.publicPath = './_nuxt/';
            }
            config.resolve.alias['src'] = path.resolve(__dirname, '..', 'administration/src/');
            config.resolve.alias['less'] = path.resolve(__dirname, '..', 'administration/src/app/assets/less');
            config.resolve.alias['vue'] = __dirname + '/node_modules/vue/dist/vue.common';
            config.resolve.extensions.push('.less', '.twig');
            config.module.rules.push({
                test: /\.(html|twig)$/,
                loader: 'html-loader'
            });
        }
    },
    generate: {
        dir: getPathFromRoot('build/artifacts/component-library'),
        routes: async () => {
             // Check if we're having a config file, otherwise set the config
            if (fs.existsSync(path.resolve(__dirname, configFileName))) {
                config = require(path.resolve(__dirname, configFileName));
            } else {
                console.warn(`No config file "${configFileName}" in project root found`);
                config = {};
                config.components = 'src/components/**/*.js';
                config.lessVariables = '';
            }

            return fileParser(config).then((files) => {
                return files.reduce((accumulator, file) => {
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
