const { join, resolve } = require('path');

const srcPath = join(__dirname, '..', '..');
const Shopware = require(resolve(join(srcPath, 'src/core/shopware.js'))); // eslint-disable-line

module.exports = (() => {
    global.Shopware = Shopware;
    require(resolve(srcPath, 'src/app/mixin/index.js')); // eslint-disable-line
    require(resolve(srcPath, 'src/app/directive/index.js')); // eslint-disable-line
    require(resolve(srcPath, 'src/app/filter/index.js')); // eslint-disable-line
})();
