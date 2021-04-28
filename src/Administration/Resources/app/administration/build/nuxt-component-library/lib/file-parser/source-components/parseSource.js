const espree = require('espree');

module.exports = (source) => {
    return espree.parse(source, {
        comment: true,
        ecmaVersion: 2020,
        sourceType: 'module',
        loc: true
    });
};
