/**
 * @sw-package framework
 */

export const unknownOptionError = {
    msg: /Given value "\w*|\d*" does not exist in given options/,
    method: 'warn',
};

export const missingGetListMethod = {
    msg: '[Listing Mixin] When using the listing mixin you have to implement your custom "getList()" method.',
    method: 'warn',
};

export const sendTimeoutExpired = {
    msg: 'Send timeout expired. It could be possible that no handler for the postMessage request exists or that the handler freezed.',
    method: 'error',
};

export const deprecatedTabComponent = {
    method: 'warn',
    msgCheck: (_, msg1) => {
        if (typeof msg1 !== 'string') {
            return false;
        }

        return msg1.includes('The old usage of "sw-tabs" is deprecated');
    },
};

export const deprecatedPopoverComponent = {
    method: 'warn',
    msgCheck: (_, msg1) => {
        if (typeof msg1 !== 'string') {
            return false;
        }

        return msg1.includes('The old usage of "sw-popover" is deprecated');
    },
};

// Vue 3 component resolution warnings for non-registered components in tests.
// sw-block and sw-block-parent are excluded: they are registered globally by the Jest setup, and
// silencing them is what let an unresolved <sw-block> add a DOM element the application does not have.
export const unresolvedComponentWarning = {
    method: 'warn',
    msgCheck: (msg0) => {
        if (typeof msg0 !== 'string') {
            return false;
        }

        if (/Failed to resolve component: sw-block(-parent)?(?![-\w])/.test(msg0)) {
            return false;
        }

        return msg0?.includes('Failed to resolve component');
    },
};
