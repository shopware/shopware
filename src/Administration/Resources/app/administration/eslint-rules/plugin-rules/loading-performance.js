/**
 * @sw-package framework
 */
const { createJiti } = require('jiti');

const jiti = createJiti(__filename);
const rules = jiti('../../scripts/extensionTooling/loading-performance/rules.ts');

module.exports = rules.default ?? rules;
