/**
 * @sw-package framework
 *
 * Jest resolves `shopware:data` here. See `create-module.js`.
 */

const createShopwareVirtualModule = require('./create-module');

module.exports = createShopwareVirtualModule('shopware:data');
