/**
 * @sw-package framework
 */

const url = require('url');
const { resolveStub } = require('./virtual-shopware-modules/stubs');

const VIRTUAL_MODULE = /^shopware:[a-z]+(\/.+)?$/;

module.exports = (request, options) => {
    const virtualModule = VIRTUAL_MODULE.test(request) ? resolveStub(request) : undefined;

    if (virtualModule) {
        return virtualModule;
    }

    // Remove any query parameters in the request path
    // (e.g. ?worker, which Vite uses for worker imports)
    if (request.includes('?')) {
        return options.defaultResolver(url.parse(request).pathname, options);
    }

    return options.defaultResolver(request, options);
};
