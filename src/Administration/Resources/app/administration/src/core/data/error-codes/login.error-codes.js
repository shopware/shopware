const title = 'global.default.error';

const codes = {
    2: 'messageUnsupportedGrantType',
    3: 'messageInvalidRequest',
    4: 'messageInvalidClient',
    5: 'messageInvalidScope',
    6: 'messageInvalidCredentials',
    7: 'messageServerError',
    8: 'messageInvalidRefreshToken',
    9: 'messageAccessDenied',
    // Error code 10 technically means invalid grant,
    // but this error is returned for invalid credentials
    // see https://github.com/thephpleague/oauth2-server/pull/967
    10: 'messageInvalidCredentials',
};

/**
 * Get the message and title associated to an error code.
 *
 * @param {Number} code
 * @param {String} prefix
 * @returns {Object}
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function getErrorCode(code, prefix = 'sw-login.index.') {
    return {
        message: codes[code] ? `${prefix}${codes[code]}` : '',
        title,
    };
}
