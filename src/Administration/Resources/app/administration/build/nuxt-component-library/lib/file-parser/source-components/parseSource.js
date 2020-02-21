const esprima = require('espree');

module.exports = (source) => {
    return esprima.parse(source, {
        comment: true,
        ecmaVersion: 2019,
        sourceType: 'module'
    });
};
