/**
 * @sw-package framework
 */

const { createJiti } = require('jiti');

// CommonJS consumers (Jest transformer, ESLint rules, Vite config) load the TypeScript entry through jiti.
const transformModule = createJiti(__filename)('./index.ts');

module.exports = transformModule.default ?? transformModule;
