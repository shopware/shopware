var path = require('path');

/**
 * Simple loader which adds a new property `__file` with the relative path of the module to the second argument
 * of the `Shopware.Component.register` call.
 */
module.exports = function(content) {
    var modulePath = this.resourcePath.replace(path.resolve(__dirname, '../../') + '/', '');
    content = content.replace(/Component\.register\((.*?),(.\{)/gi, function(match, p1) {
        return `Component.register(${p1}, { __file: '${modulePath}',`;
    });
    return content;
};