/**
 * @package admin
 */

export const unknownOptionError = {
    msg: /Given value "\w*|\d*" does not exists in given options/,
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
