const fs = require('fs');
const path = require('path');
const fileParser = require(__dirname + '/lib/file-parser');

const configFileName = 'component-library.conf.js';
let config;

module.exports = {
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
        extend(config) {
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
                return files.map((file) => {
                    console.log(file.source.name);
                    return `/components/${file.source.name}`;
                });
            });
        }
    }
};
