/**
 * @sw-package framework
 *
 * Jest resolves `shopware:mixins` here. See `create-module.js`.
 */

const createShopwareVirtualModule = require('./create-module');

module.exports = createShopwareVirtualModule('shopware:mixins');
