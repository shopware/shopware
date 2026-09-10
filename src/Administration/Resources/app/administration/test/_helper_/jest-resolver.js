/**
 * @sw-package framework
 */

const url = require('url');
const { stubPath } = require('./virtual-shopware-modules/stubs');

const VIRTUAL_MODULE = /^shopware:[a-z]+(\/.+)?$/;

module.exports = (request, options) => {
    // Written up front by jest.config.ts, so this only has to point at them.
    if (VIRTUAL_MODULE.test(request)) {
        return stubPath(request);
    }

    // Remove any query parameters in the request path
    // (e.g. ?worker, which Vite uses for worker imports)
    if (request.includes('?')) {
        return options.defaultResolver(url.parse(request).pathname, options);
    }

    return options.defaultResolver(request, options);
};
