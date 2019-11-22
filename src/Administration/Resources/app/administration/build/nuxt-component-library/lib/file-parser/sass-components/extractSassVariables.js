const fs = require('fs');

module.exports = {
    extractSassVariables,
    extractImportFile,
    getFileContent,
    parseSassFile
};

function extractImportFile(importList) {
    if (!importList || !importList.length) {
        return null;
    }

    const definedSassImport = importList.reduce((accumulator, item) => {
        if (item.indexOf('.scss') !== -1) {
            accumulator = item;
        }
        return accumulator;
    }, null);

    return definedSassImport;
}

function getFileContent(fileName) {
    return fs.readFileSync(fileName, {
        encoding: 'utf-8'
    });
}

function extractSassVariables(content) {
    const RE_SASS_VARIABLES = /^(\$.+)?:\s+(.*);/gm;

    let matches = content.match(RE_SASS_VARIABLES);
    if (!matches) {
        return [];
    }

    matches = matches.map((item) => {
        const groups = new RegExp(RE_SASS_VARIABLES).exec(item);

        return {
            key: groups[1],
            value: groups[2]
        };
    });

    return matches;
}

function parseSassFile(file, importList, globalVariables) {
    let importFile = extractImportFile(importList);

    if (!importFile) {
        return [];
    }

    importFile = getFullFilePath(file.directory, importFile);
    const fileContent = getFileContent(importFile);
    const variables = extractSassVariables(fileContent);

    return mapVariablesToGlobalVariables(variables, globalVariables);
}

function mapVariablesToGlobalVariables(variables, globalVariables) {
    return variables.map((item) => {
        const value = item.value;
        const key = item.key;

        if (value.startsWith('$') && globalVariables.has(value)) {
            return {
                key,
                value: `${value} (${globalVariables.get(value)})`
            };
        }
        return item;
    });
}

function getFullFilePath(basePath, importFile) {
    return importFile.replace('.', basePath);
}
