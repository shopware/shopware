/**
 * @sw-package framework
 *
 * Jest resolves `shopware:utils` here. See `create-module.js`.
 */

const createShopwareVirtualModule = require('./create-module');

module.exports = createShopwareVirtualModule('shopware:utils');
