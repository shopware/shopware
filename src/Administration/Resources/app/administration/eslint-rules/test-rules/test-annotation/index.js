/**
 * @sw-package framework
 */

const { testCallKind, annotationOf } = require('./annotation');
const { isAcceptedSource, EXPECTED_SOURCES } = require('./sources');

const DOCS = 'See technical-docs/11-code-quality/04-test-annotations.md.';
const MIN_EXCEPTION_LENGTH = 20;

/**
 * Checks the syntax of test annotations where they are used.
 * An untagged test is fine: tagging is a convention that this rule does not require.
 */
module.exports = {
    meta: {
        type: 'suggestion',
        docs: {
            description:
                'Checks that a tagged test uses exactly one of @regression, @behavior or @characterization, with a valid @see source for the first two.',
        },
        schema: [
            {
                type: 'object',
                additionalProperties: false,
                properties: {
                    // Absolute path of the repository root, to resolve `@see adr/….md`.
                    repoRoot: { type: 'string' },
                },
            },
        ],
        messages: {
            multipleKinds: `Use exactly one of @regression, @behavior or @characterization. ${DOCS}`,
            characterizationOnDescribe: `@characterization is not inherited. Tag each test instead of the describe. ${DOCS}`,
            missingSee: `@{{ kind }} needs a @see here or on an enclosing describe tagged @{{ kind }}. ${DOCS}`,
            invalidSee: `@see for @{{ kind }} must be {{ expected }}. Add @seeException <reason> if no such source exists. ${DOCS}`,
            shortException: `@seeException needs a reason of at least ${MIN_EXCEPTION_LENGTH} characters that says why the source counts. ${DOCS}`,
        },
    },

    create(context) {
        const { repoRoot } = context.options[0] ?? {};
        const { sourceCode } = context;

        function validateSources(node, kind, { see, seeException }) {
            if (seeException !== undefined) {
                if (seeException.length < MIN_EXCEPTION_LENGTH) {
                    context.report({ node, messageId: 'shortException' });
                }
                return;
            }

            if (see.some((source) => !isAcceptedSource(kind, source, repoRoot))) {
                context.report({ node, messageId: 'invalidSee', data: { kind, expected: EXPECTED_SOURCES[kind] } });
            }
        }

        /**
         * Whether an enclosing describe with the same tag links a source this call can share.
         */
        function inheritsSee(node, kind) {
            return sourceCode
                .getAncestors(node)
                .filter((ancestor) => ancestor.type === 'CallExpression' && testCallKind(ancestor) === 'describe')
                .map((describe) => annotationOf(describe, sourceCode))
                .some(({ kinds, see }) => kinds[0] === kind && see.length > 0);
        }

        return {
            CallExpression(node) {
                const callKind = testCallKind(node);

                if (!callKind) {
                    return;
                }

                const annotation = annotationOf(node, sourceCode);
                const [kind] = annotation.kinds;

                if (annotation.kinds.length > 1) {
                    context.report({ node, messageId: 'multipleKinds' });
                } else if (kind === 'characterization') {
                    if (callKind === 'describe') {
                        context.report({ node, messageId: 'characterizationOnDescribe' });
                    }
                } else if (kind && annotation.see.length > 0) {
                    validateSources(node, kind, annotation);
                } else if (kind && !inheritsSee(node, kind)) {
                    context.report({ node, messageId: 'missingSee', data: { kind } });
                }
            },
        };
    },
};
