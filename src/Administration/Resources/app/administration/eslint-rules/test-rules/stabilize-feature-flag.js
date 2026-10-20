/**
 * @sw-package framework
 */

// Bring a flag into the runtime's upper snake case form so `v6.8.0.0` and `V6_8_0_0` compare equal.
function normalizeFeatureFlag(featureFlag) {
    return featureFlag.toUpperCase().replace(/[.:-]/g, '_');
}

// The stabilized flag names are handcrafted and passed through the ESLint config (see
// eslint.config.ts). Every listed flag has shipped and is permanently on, so activating it in a
// test is a no-op and can be removed.
function getStabilizedFlags(flagList) {
    return new Set((flagList ?? []).map(normalizeFeatureFlag));
}

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

        // collect all consecutive stabilized flags in one go for one consecutive removal range
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
            description: 'Auto-remove stabilized feature flags from it.activeFeatureFlags calls.',
        },
        fixable: 'code',
        schema: [
            {
                type: 'object',
                additionalProperties: false,
                properties: {
                    // Handcrafted list of stabilized feature-flag names. Each is removed from
                    // it.activeFeatureFlags activations.
                    stabilizedFlags: {
                        type: 'array',
                        items: { type: 'string' },
                    },
                },
            },
        ],
        messages: {
            stabilizedFeatureFlag: "Feature flag '{{ featureFlag }}' is stable and no longer needs to be activated.",
            arrayLiteralRequired: 'it.activeFeatureFlags(...) requires an inline array of string literals.',
        },
    },

    create(context) {
        const sourceCode = context.sourceCode;
        const stabilizedFlags = getStabilizedFlags(context.options[0]?.stabilizedFlags);

        // Nothing to enforce without a configured flag list.
        if (stabilizedFlags.size === 0) {
            return {};
        }

        return {
            CallExpression(node) {
                const activeFeatureFlagsCall = findActiveFeatureFlagsCall(node);

                if (!activeFeatureFlagsCall) {
                    return;
                }

                const [featureFlags] = activeFeatureFlagsCall.arguments;

                // The autofixer can only reason about an inline array of string literals; anything else
                // (a variable, a spread, a computed value) must be made explicit.
                if (
                    featureFlags?.type !== 'ArrayExpression' ||
                    featureFlags.elements.some((element) => element === null || element.type !== 'Literal')
                ) {
                    context.report({
                        node: activeFeatureFlagsCall,
                        messageId: 'arrayLiteralRequired',
                    });
                    return;
                }

                const activeFeatureFlags = featureFlags.elements.map((element, activeIndex) => ({
                    node: element,
                    activeIndex,
                    normalized: normalizeFeatureFlag(String(element.value)),
                }));

                const stabilizedFeatureFlags = activeFeatureFlags.filter((flag) => stabilizedFlags.has(flag.normalized));

                if (stabilizedFeatureFlags.length === 0) {
                    return;
                }

                context.report({
                    node: stabilizedFeatureFlags[0].node,
                    messageId: 'stabilizedFeatureFlag',
                    data: { featureFlag: String(stabilizedFeatureFlags[0].node.value) },
                    fix(fixer) {
                        if (activeFeatureFlags.length === stabilizedFeatureFlags.length) {
                            // Replace only the flag call, so a chained `.each(table)` survives.
                            return fixer.replaceText(activeFeatureFlagsCall, 'it');
                        }

                        return getRemovalRanges(
                            sourceCode,
                            activeFeatureFlags.map((flag) => flag.node),
                            stabilizedFeatureFlags,
                        ).map((range) => fixer.removeRange(range));
                    },
                });
            },
        };
    },
};
