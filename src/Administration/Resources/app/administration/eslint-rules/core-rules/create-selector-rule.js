/**
 * @sw-package framework
 */

/**
 * Builds a rule that reports every node matching one selector. Unlike a `no-restricted-syntax` entry, each of
 * these rules has its own rule ID, so eslint-suppressions.json counts its violations separately.
 */
module.exports = function createSelectorRule({ selector, message, description, type = 'problem' }) {
    return {
        meta: {
            type,
            docs: { description },
            schema: [],
            messages: { selectorMatch: message },
        },

        create(context) {
            return {
                [selector](node) {
                    context.report({ node, messageId: 'selectorMatch' });
                },
            };
        },
    };
};
