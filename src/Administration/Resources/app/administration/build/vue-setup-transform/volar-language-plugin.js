/**
 * @sw-package framework
 */

const { createJiti } = require('jiti');

const jiti = createJiti(__filename);
const volarPluginModule = jiti('./volar-language-plugin.ts');

module.exports = volarPluginModule.default ?? volarPluginModule;
