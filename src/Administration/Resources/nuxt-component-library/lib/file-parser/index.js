const fs = require('fs');
const path = require('path');

const utils = require('./utils');
const processFile = require('./process-file');

const {
    getFileContent,
    extractLessVariables
} = require('./less-components/extractLessVariables');

module.exports = (config) => {
    const globalVariablesMap = new Map();
    if (config.lessVariables && config.lessVariables.length) {
        const lessGlobalFileContent = getFileContent(config.lessVariables);
        const globalLessVariables = extractLessVariables(lessGlobalFileContent);

        globalLessVariables.forEach((item) => {
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
