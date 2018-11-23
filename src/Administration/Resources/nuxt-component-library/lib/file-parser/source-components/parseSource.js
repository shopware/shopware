const esprima = require('esprima');

module.exports = (source) => {
    return esprima.parseModule(source, {
        comment: true
    });
};