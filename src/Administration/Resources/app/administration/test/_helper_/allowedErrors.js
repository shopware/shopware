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
