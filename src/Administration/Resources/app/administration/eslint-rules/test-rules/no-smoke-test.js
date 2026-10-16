/**
 * @sw-package framework
 */

const TEST_FUNCTIONS = new Set([
    'it',
    'test',
]);
const EXISTENCE_MATCHERS = new Set([
    'toBeTruthy',
    'toBeDefined',
]);

// Walks `it.only`, `it.each([...])` and `it.activeFeatureFlags([...])` down to `it`.
function getRootName(node) {
    let current = node;

    while (current.type === 'MemberExpression' || current.type === 'CallExpression') {
        current = current.type === 'MemberExpression' ? current.object : current.callee;
    }

    return current.type === 'Identifier' ? current.name : null;
}

function isTestCall(node) {
    const callback = node.arguments[1];

    return (
        TEST_FUNCTIONS.has(getRootName(node.callee)) &&
        !!callback &&
        ['ArrowFunctionExpression', 'FunctionExpression'].includes(callback.type)
    );
}

// `expect*` helpers count as assertions, like in the `jest/expect-expect` config.
function isAssertionCall(node) {
    return node.callee.type === 'Identifier' && node.callee.name.startsWith('expect');
}

function isVmAccess(subject) {
    return subject?.type === 'MemberExpression' && !subject.computed && subject.property.name === 'vm';
}

// Only the mounted `wrapper` itself: `button.exists()` on a found element checks what renders.
function isWrapperExistsCall(subject) {
    return (
        subject?.type === 'CallExpression' &&
        subject.arguments.length === 0 &&
        subject.callee.type === 'MemberExpression' &&
        !subject.callee.computed &&
        subject.callee.property.name === 'exists' &&
        subject.callee.object.type === 'Identifier' &&
        subject.callee.object.name === 'wrapper'
    );
}

function getMatcherCall(expectCall) {
    const matcher = expectCall.parent;

    if (matcher.type !== 'MemberExpression' || matcher.object !== expectCall || matcher.computed) {
        return null;
    }

    return matcher.parent.type === 'CallExpression' && matcher.parent.callee === matcher ? matcher.parent : null;
}

function isTrueMatcher(matcherCall) {
    const name = matcherCall.callee.property.name;
    const [expected] = matcherCall.arguments;

    return name === 'toBeTruthy' || (name === 'toBe' && expected?.type === 'Literal' && expected.value === true);
}

function isExistenceAssertion(expectCall) {
    const [subject] = expectCall.arguments;
    const matcherCall = getMatcherCall(expectCall);

    if (expectCall.callee.name !== 'expect' || !matcherCall) {
        return false;
    }

    if (isVmAccess(subject)) {
        return EXISTENCE_MATCHERS.has(matcherCall.callee.property.name);
    }

    return isWrapperExistsCall(subject) && isTrueMatcher(matcherCall);
}

module.exports = {
    meta: {
        type: 'problem',
        docs: {
            description: 'Disallow tests whose only assertion is that the component instance exists',
        },
        schema: [],
        messages: {
            smokeTest: 'This test only checks that the component mounted. Assert what it renders, emits or requests.',
        },
    },

    create(context) {
        const tests = [];

        return {
            CallExpression(node) {
                if (isTestCall(node)) {
                    tests.push({ node, assertions: 0, existenceAssertions: 0 });

                    return;
                }

                if (tests.length === 0 || !isAssertionCall(node)) {
                    return;
                }

                const currentTest = tests[tests.length - 1];
                currentTest.assertions += 1;

                if (isExistenceAssertion(node)) {
                    currentTest.existenceAssertions += 1;
                }
            },

            'CallExpression:exit'(node) {
                if (tests[tests.length - 1]?.node !== node) {
                    return;
                }

                const { assertions, existenceAssertions } = tests.pop();

                if (assertions > 0 && assertions === existenceAssertions) {
                    context.report({ node, messageId: 'smokeTest' });
                }
            },
        };
    },
};
