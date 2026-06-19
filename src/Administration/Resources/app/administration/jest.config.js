/**
 * @sw-package framework
 */

const { createJiti } = require('jiti');

const jiti = createJiti(__filename);
const config = jiti('./jest.config.ts');

module.exports = config.default ?? config;
