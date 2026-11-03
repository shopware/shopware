/**
 * @sw-package framework
 */

/**
 * Escapes a literal string for use inside a RegExp source.
 *
 * @example
 * escapeRegExp('a.b'); // 'a\\.b'
 */
function escapeRegExp(text) {
    return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

/**
 * Registry-backed rules append the migration hint, description and removal version to a case's base
 * message. This keeps each case's expected message as the start of the report and still requires the
 * registry context after it, so a drifted base message fails the case.
 *
 * @example
 * withRegistryMessageContext([{ code, errors: [{ message: '"sw-alert" is deprecated.' }] }]);
 * // errors: [{ message: /^"sw-alert" is deprecated\.\n[\s\S]*Removed in Shopware/ }]
 */
function withRegistryMessageContext(cases) {
    return cases.map((testCase) => {
        return {
            ...testCase,
            errors: testCase.errors?.map((error) => {
                return {
                    ...error,
                    message: toRegistryMessage(error.message),
                };
            }),
        };
    });
}

function toRegistryMessage(message) {
    if (typeof message === 'string') {
        return new RegExp(`^${escapeRegExp(message)}\\n[\\s\\S]*Removed in Shopware`);
    }

    if (message instanceof RegExp) {
        return new RegExp(`(?:${message.source})[\\s\\S]*Removed in Shopware`, message.flags);
    }

    return /Removed in Shopware/;
}

module.exports = {
    withRegistryMessageContext,
};
