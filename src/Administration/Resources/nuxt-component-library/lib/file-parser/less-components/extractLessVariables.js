const fs = require('fs');

module.exports = {
    extractImportFile,
    getFileContent,
    extractLessVariables,
    getFullFilePath,
    parseLessFile
};

function extractImportFile(importList) {
    if (!importList || !importList.length) {
        return null;
    }

    const definedLessImport = importList.reduce((accumulator, item) => {
        if (item.indexOf('.less') !== -1) {
            accumulator = item;
        }
        return accumulator;
    }, null);

    return definedLessImport;
}

function getFileContent(fileName) {
    return fs.readFileSync(fileName, {
        encoding: 'utf-8'
    });
}

function extractLessVariables(content) {
    const RE_LESS_VARIABLES = /(@.*):\s*(.*);/g;

    let matches = content.match(RE_LESS_VARIABLES);
    if (!matches) {
        return [];
    }
    
    matches = matches.map((item) => {
        const groups = new RegExp(RE_LESS_VARIABLES).exec(item);

        return {
            key: groups[1],
            value: groups[2]
        };
    });

    return matches;
}

function getFullFilePath(basePath, importFile) {
    return importFile.replace('.', basePath);
}

function mapVariablesToGlobalVariables(variables, globalVariables) {
    return variables.map((item) => {
        const value = item.value;
        const key = item.key;

        if (value.startsWith('@') && globalVariables.has(value)) {
            return {
                key,
                value: `${value} (${globalVariables.get(value)})`
            };
        }
        return item;
    });
}

function parseLessFile(file, importList, globalVariables) {
    let importFile = extractImportFile(importList);

    if (!importFile) {
        return [];
    }

    importFile = getFullFilePath(file.directory, importFile);
    const fileContent = getFileContent(importFile);
    const variables = extractLessVariables(fileContent);

    return mapVariablesToGlobalVariables(variables, globalVariables);
}