const fs = require('fs');
const path = require('path');

const utils = require('./utils');
const processFile = require('./process-file');

const {
    getFileContent,
    extractLessVariables
} = require('./less-components/extractLessVariables');

const {
    extractSassVariables
} = require('./sass-components/extractSassVariables');

module.exports = (config) => {
    // Registry for all global variables (less & sass)
    const globalVariablesMap = new Map();

    // Get global less variables
    if (config.lessVariables && config.lessVariables.length) {
        const lessGlobalFileContent = getFileContent(config.lessVariables);
        const globalLessVariables = extractLessVariables(lessGlobalFileContent);

        globalLessVariables.forEach((item) => {
            globalVariablesMap.set(item.key, item.value);
        });
    }

    // Get global sass variables
    if (config.sassVariables && config.sassVariables.length) {
        const sassGlobalFileContent = getFileContent(config.sassVariables);
        const globalSassVariables = extractSassVariables(sassGlobalFileContent);

        globalSassVariables.forEach((item) => {
            globalVariablesMap.set(item.key, item.value);
        });
    }

    return utils.glob(config.components).then((files) => {
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
