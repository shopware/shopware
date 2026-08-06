/**
 * @sw-package framework
 */

function isActiveFeatureFlagsCall(node) {
    return (
        node.type === 'MemberExpression' &&
        node.computed === false &&
        node.object.type === 'Identifier' &&
        node.object.name === 'it' &&
        node.property.type === 'Identifier' &&
        node.property.name === 'activeFeatureFlags'
    );
}

/**
 * Resolves the `it.activeFeatureFlags([...])` call behind a registered test, for both shapes:
 *
 *   it.activeFeatureFlags([...])(name, fn)
 *   it.activeFeatureFlags([...]).each(table)(name, fn)
 *
 * In the chained form the outer callee is the `.each(table)` call, so the flag call sits one level
 * deeper. Returns null when the node is not a feature-flagged test.
 */
function findActiveFeatureFlagsCall(node) {
    if (node.callee.type !== 'CallExpression') {
        return null;
    }

    if (isActiveFeatureFlagsCall(node.callee.callee)) {
        return node.callee;
    }

    const eachCallee = node.callee.callee;

    if (
        eachCallee.type === 'MemberExpression' &&
        eachCallee.computed === false &&
        eachCallee.property.type === 'Identifier' &&
        eachCallee.property.name === 'each' &&
        eachCallee.object.type === 'CallExpression' &&
        isActiveFeatureFlagsCall(eachCallee.object.callee)
    ) {
        return eachCallee.object;
    }

    return null;
}

function getRemovalRanges(sourceCode, featureFlags, stabilizedFeatureFlags) {
    const removalRanges = [];

    for (let index = 0; index < stabilizedFeatureFlags.length; index += 1) {
        const firstFeatureFlag = stabilizedFeatureFlags[index];
        let lastFeatureFlag = firstFeatureFlag;

        // collect all non-experimental flags in one go for one consecutive removal range
        while (stabilizedFeatureFlags[index + 1]?.activeIndex === lastFeatureFlag.activeIndex + 1) {
            index += 1;
            lastFeatureFlag = stabilizedFeatureFlags[index];
        }

        const nextFeatureFlag = featureFlags[lastFeatureFlag.activeIndex + 1];
        if (nextFeatureFlag) {
            const comma = sourceCode.getTokenAfter(lastFeatureFlag.node, (token) => token.value === ',');
            const separator = sourceCode.text.slice(comma.range[1], nextFeatureFlag.range[0]);
            let rangeStart = firstFeatureFlag.node.range[0];
            let rangeEnd = nextFeatureFlag.range[0];

            if (separator.trim() !== '') {
                const previousToken = sourceCode.getTokenBefore(firstFeatureFlag.node);
                const leadingWhitespace = sourceCode.text.slice(previousToken.range[1], firstFeatureFlag.node.range[0]);

                rangeStart = leadingWhitespace.trim() === '' ? previousToken.range[1] : rangeStart;
                rangeEnd = comma.range[1];
            }

            removalRanges.push([
                rangeStart,
                rangeEnd,
            ]);
            continue;
        }

        const comma = sourceCode.getTokenBefore(firstFeatureFlag.node, (token) => token.value === ',');
        removalRanges.push([
            comma.range[0],
            lastFeatureFlag.node.range[1],
        ]);
    }

    return removalRanges;
}

module.exports = {
    meta: {
        type: 'suggestion',
        docs: {
            description: 'Remove a stabilized feature flag from it.activeFeatureFlags calls',
        },
        fixable: 'code',
        schema: [
            {
                type: 'string',
                minLength: 1,
            },
        ],
        messages: {
            stabilizedFeatureFlag: "Feature flag '{{ featureFlag }}' is stable and no longer needs to be activated.",
        },
    },

    create(context) {
        const sourceCode = context.sourceCode;
        const [stabilizedFeatureFlag] = context.options;

        return {
            CallExpression(node) {
                const activeFeatureFlagsCall = findActiveFeatureFlagsCall(node);

                if (!activeFeatureFlagsCall) {
                    return;
                }

                const [featureFlags] = activeFeatureFlagsCall.arguments;
                if (featureFlags?.type !== 'ArrayExpression') {
                    return;
                }

                const activeFeatureFlags = featureFlags.elements.filter(Boolean);
                const stabilizedFeatureFlags = activeFeatureFlags
                    .map((featureFlag, activeIndex) => ({ node: featureFlag, activeIndex }))
                    .filter(({ node: featureFlag }) => {
                        return featureFlag.type === 'Literal' && featureFlag.value === stabilizedFeatureFlag;
                    });

                if (stabilizedFeatureFlags.length === 0) {
                    return;
                }

                context.report({
                    node: stabilizedFeatureFlags[0].node,
                    messageId: 'stabilizedFeatureFlag',
                    data: { featureFlag: stabilizedFeatureFlag },
                    fix(fixer) {
                        if (activeFeatureFlags.length === stabilizedFeatureFlags.length) {
                            // Replace only the flag call, so a chained `.each(table)` survives.
                            return fixer.replaceText(activeFeatureFlagsCall, 'it');
                        }

                        return getRemovalRanges(sourceCode, activeFeatureFlags, stabilizedFeatureFlags).map((range) =>
                            fixer.removeRange(range),
                        );
                    },
                });
            },
        };
    },
};
