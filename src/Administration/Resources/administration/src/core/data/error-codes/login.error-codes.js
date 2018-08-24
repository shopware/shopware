const title = 'sw-login.index.titleErrorMessage';

const codes = {
    2: 'messageUnsupportedGrantType',
    3: 'messageInvalidRequest',
    4: 'messageInvalidClient',
    5: 'messageInvalidScope',
    6: 'messageInvalidCredentials',
    7: 'messageServerError',
    8: 'messageInvalidRefreshToken',
    9: 'messageAccessDenied',
    10: 'messageInvalidGrant'
};

/**
 * Get the message and title associated to an error code.
 *
 * @param {Number} code
 * @param {String} prefix
 * @returns {Object}
 */
export default function getErrorCode(code, prefix = 'sw-login.index.') {
    return {
        message: codes[code] ? `${prefix}${codes[code]}` : '',
        title
    };
}
