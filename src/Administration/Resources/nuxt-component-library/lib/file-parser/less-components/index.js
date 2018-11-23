const {
    extractImportFile,
    getFileContent,
    extractLessVariables,
    getFullFilePath,
    parseLessFile
} = require('./extractLessVariables');

module.exports = (file, importList, variables) => {
    return parseLessFile(file, importList, variables);
};
