const {
    parseLessFile
} = require('./extractLessVariables');

module.exports = (file, importList, variables) => {
    return parseLessFile(file, importList, variables);
};
