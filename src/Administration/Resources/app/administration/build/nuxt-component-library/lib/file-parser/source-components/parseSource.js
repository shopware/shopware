const espree = require('espree');

module.exports = (source) => {
    return espree.parse(source, {
        comment: true,
        ecmaVersion: 2019,
        sourceType: 'module',
        loc: true
    });
};
