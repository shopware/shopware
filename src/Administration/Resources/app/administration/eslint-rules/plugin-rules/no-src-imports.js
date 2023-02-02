/* eslint-disable max-len */
let refactorAlias = false;
if (global.featureFlags && global.featureFlags.hasOwnProperty('FEATURE_NEXT_11634')) {
    refactorAlias = global.featureFlags.FEATURE_NEXT_11634;
}

module.exports = {
    create(context) {
        return {
            ImportDeclaration(node) {
                const invalidNodeSources = [];

                if (refactorAlias) {
                    invalidNodeSources.push(node.source.value.startsWith('@administration/'));
                } else {
                    invalidNodeSources.push([
                        node.source.value.startsWith('src/'),
                        node.source.value.startsWith('assets/'),
                    ]);
                }

                if (invalidNodeSources.includes(true)) {
                    context.report({
                        loc: node.source.loc.start,
                        message: `\
You can't use imports directly from the Shopware Core via "${node.source.value}". \
Use the global Shopware object directly instead (https://developer.shopware.com/docs/guides/plugins/plugins/administration/the-shopware-object)`,
                    });
                }
            },
        };
    },
};
