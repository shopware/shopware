const fs = require('fs');
const path = require('path');

const utils = require('./utils');
const processFile = require('./process-file');

const {
    getFileContent,
    extractSassVariables
} = require('./sass-components/extractSassVariables');

module.exports = (config) => {
    // Registry for all global sass variables
    const globalVariablesMap = new Map();

    // Get global sass variables
    if (config.sassVariables && config.sassVariables.length) {
        const sassGlobalFileContent = getFileContent(config.sassVariables);
        const globalSassVariables = extractSassVariables(sassGlobalFileContent);

        globalSassVariables.forEach((item) => {
            globalVariablesMap.set(item.key, item.value);
        });
    }

    return utils.glob(
        config.components,
        {
            ignore: [
                '**/*.spec.js',
                '**/*.spec.vue2.js',
                '**/*.js.snap',
                '**/_fixtures/*.js',
                '**/_sw-admin-menu-item/*.js',
                '**/fixtures/*.js',
            ],
        }).then((files) => {
        return files.map((file) => {
            const source = fs.readFileSync(file, {
                encoding: 'utf-8'
            });

            return {
                type: path.dirname(file).split('/').slice(-2)[0],
                directory: path.dirname(file),
                fileName: path.basename(file),
                path: file,
                source: source
            };
        });
    }).then((sourceList) => {
        return sourceList.map((file) => {
            file.source = processFile(file, globalVariablesMap);
            return file;
        });
    });
};
