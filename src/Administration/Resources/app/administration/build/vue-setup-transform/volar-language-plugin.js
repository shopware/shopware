/**
 * @sw-package framework
 */

const { parse } = require('@vue/compiler-sfc');
const { transformShopwareSetupSfc } = require('./index');

/**
 * @typedef {object} VolarSfcParserPlugin
 * @property {2} version
 * @property {string} name
 * @property {(fileName: string, content: string) => import('@vue/compiler-sfc').SFCParseResult | undefined} parseSFC
 */

/**
 * Volar/vue-tsc call parseSFC before producing virtual TypeScript files.
 * Returning the transformed descriptor lets editor tooling see the same
 * standard Vue syntax that Vite and Jest receive, while unsupported Shopware setup
 * shapes still fail through the shared validator.
 *
 * @returns {VolarSfcParserPlugin}
 */
module.exports = function shopwareSetupVolarPlugin() {
    return {
        version: 2,
        name: 'shopware-setup',

        parseSFC(fileName, content) {
            const result = transformShopwareSetupSfc(content, fileName);

            if (!result) {
                return undefined;
            }

            return parse(result.code, { filename: fileName });
        },
    };
};
