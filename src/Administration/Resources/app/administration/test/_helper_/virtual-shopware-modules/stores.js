/**
 * @sw-package framework
 *
 * Jest resolves `shopware:stores` here. See `create-module.js`.
 */

const createShopwareVirtualModule = require('./create-module');

module.exports = createShopwareVirtualModule('shopware:stores');
