/**
 * @sw-package framework
 */

const { createJiti } = require('jiti');

// Webpack/Vite integrations still load this transform through CommonJS. Keep the implementation in
// TypeScript and let jiti bridge the runtime loader until the surrounding build tooling is ESM-only.
const jiti = createJiti(__filename);
const transformModule = jiti('./index.ts');

module.exports = transformModule.default ?? transformModule;
