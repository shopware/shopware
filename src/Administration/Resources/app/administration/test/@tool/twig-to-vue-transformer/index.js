const crypto = require('crypto');
const twigRenderer = require('./twig-renderer');

function getCacheKey(fileData, filePath, configStr) {
    return crypto.createHash('md5')
        .update(fileData + filePath + configStr, 'utf8')
        .digest('hex');
}
exports.getCacheKey = getCacheKey;

function process(src, path) {
    const compiledTemplate = twigRenderer(src, path);
    return {
        code: 'module.exports = `' + compiledTemplate + '`;' //eslint-disable-line
    };
}
exports.process = process;
