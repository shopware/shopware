/**
 * @sw-package framework
 */

const { createJiti } = require('jiti');

const jiti = createJiti(__filename);
const transformModule = jiti('./index.ts');

module.exports = transformModule.default ?? transformModule;
