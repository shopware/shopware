const {
    parseSassFile
} = require('./extractSassVariables');

module.exports = (file, importList, variables) => {
    return parseSassFile(file, importList, variables);
};
