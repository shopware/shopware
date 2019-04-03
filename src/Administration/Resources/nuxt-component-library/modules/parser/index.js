const fs = require('fs');
const path = require('path');
const glob = require('glob');
const fileParser = require('../../lib/file-parser');

module.exports = async function parserPlugin(moduleOptions) {
    const configFileName = 'component-library.conf.js';
    let config;

    // Check if we're having a config file, otherwise set the config
    if (fs.existsSync(path.resolve(__dirname, '..', '..', configFileName))) {
        config = require(path.resolve(__dirname, '..', '..', configFileName));
    } else {
        console.warn(`No config file "${configFileName}" in project root found`);
        config = {};
        config.components = 'src/components/**/*.js';
    }

    if (!config.sassVariables) {
        config.sassVariables = '';
    }

    const filesInfo = await fileParser(config);

    const options = Object.assign({},{ filesInfo: filesInfo }, moduleOptions);

    // Register plugin
    this.addPlugin({
        src: path.resolve(__dirname, 'plugin.js'),
        ssr: true,
        fileName: 'documentationParser.js',
        options
    });
}
