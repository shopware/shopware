/**
 * @sw-package framework
 */

const { collectTestCases } = require('./test-cases');
const { isAcceptedSource, EXPECTED_SOURCES } = require('./sources');
const { readBaseline, baselineKey } = require('./baseline-file');

const KINDS = [
    'regression',
    'behavior',
    'characterization',
];
const DOCS = 'See technical-docs/11-code-quality/03-test-annotations.md.';
const DEFAULT_MIN_EXCEPTION_LENGTH = 20;

/**
 * The kind tags of a doc comment; more than one is an error.
 *
 * @example
 * kindsOf({ tags: [{ name: 'regression', value: '' }] }); // ['regression']
 */
function kindsOf(doc) {
    return (doc?.tags ?? []).filter((tag) => KINDS.includes(tag.name)).map((tag) => tag.name);
}

/**
 * The values of every tag with this name, such as all `@see` targets.
 */
function tagValues(doc, name) {
    return (doc?.tags ?? []).filter((tag) => tag.name === name).map((tag) => tag.value);
}

/**
 * Whether a doc comment names its own source, so it does not inherit one from a describe.
 */
function hasEvidence(doc) {
    return tagValues(doc, 'see').length > 0 || tagValues(doc, 'seeException').length > 0;
}

module.exports = {
    meta: {
        type: 'suggestion',
        docs: {
            description:
                'Requires every test to say why it exists: @regression, @behavior or @characterization, with a @see source for the first two.',
        },
        schema: [
            {
                type: 'object',
                additionalProperties: false,
                properties: {
                    // Absolute path of the baseline that lists the untagged tests which predate this rule.
                    baseline: { type: 'string' },
                    // Absolute path of the repository root, to resolve `@see adr/….md`.
                    repoRoot: { type: 'string' },
                    minExceptionLength: { type: 'number', minimum: 1 },
                },
            },
        ],
        messages: {
            missingKind: `Add a doc comment with @regression, @behavior or @characterization to this test. ${DOCS}`,
            multipleKinds: `Use exactly one of @regression, @behavior or @characterization. ${DOCS}`,
            characterizationOnDescribe: `@characterization is not inherited. Tag each test instead of the describe. ${DOCS}`,
            missingSee: `@{{ kind }} needs a @see on the test or on an enclosing describe tagged @{{ kind }}. ${DOCS}`,
            inheritedKindMismatch: `The nearest describe with a @see is tagged @{{ inherited }}, so this @{{ kind }} test needs its own @see. ${DOCS}`,
            invalidSee: `@see for @{{ kind }} must be {{ expected }}. Add @seeException <reason> if no such source exists. ${DOCS}`,
            shortException: `@seeException needs a reason of at least {{ min }} characters that says why the source counts. ${DOCS}`,
        },
    },

    create(context) {
        const options = context.options[0] ?? {};
        const minExceptionLength = options.minExceptionLength ?? DEFAULT_MIN_EXCEPTION_LENGTH;
        const sourceCode = context.sourceCode;

        function validateEvidence(doc, kind, node) {
            const sources = tagValues(doc, 'see');
            const [exception] = tagValues(doc, 'seeException');

            if (sources.length === 0) {
                context.report({ node, messageId: 'missingSee', data: { kind } });
                return;
            }

            if (exception !== undefined) {
                if (exception.length < minExceptionLength) {
                    context.report({ node, messageId: 'shortException', data: { min: minExceptionLength } });
                }
                return;
            }

            sources
                .map((value) => value.split(/\s+/)[0])
                .filter((source) => !isAcceptedSource(kind, source, options.repoRoot))
                .forEach(() => {
                    context.report({
                        node,
                        messageId: 'invalidSee',
                        data: { kind, expected: EXPECTED_SOURCES[kind] },
                    });
                });
        }

        function checkDescribe({ node, doc }) {
            const kinds = kindsOf(doc);

            if (kinds.length > 1) {
                context.report({ node, messageId: 'multipleKinds' });
                return;
            }

            if (kinds[0] === 'characterization') {
                context.report({ node, messageId: 'characterizationOnDescribe' });
                return;
            }

            if (kinds.length === 1) {
                validateEvidence(doc, kinds[0], node);
            }
        }

        function checkTest({ node, doc, describes }, allowance) {
            const kinds = kindsOf(doc);

            if (kinds.length === 0) {
                if (allowance.consume()) {
                    return;
                }

                context.report({ node, messageId: 'missingKind' });
                return;
            }

            if (kinds.length > 1) {
                context.report({ node, messageId: 'multipleKinds' });
                return;
            }

            const [kind] = kinds;

            if (kind === 'characterization') {
                return;
            }

            if (hasEvidence(doc)) {
                validateEvidence(doc, kind, node);
                return;
            }

            const source = describes.find((describe) => hasEvidence(describe.doc));
            const inherited = kindsOf(source?.doc)[0];

            if (!source) {
                context.report({ node, messageId: 'missingSee', data: { kind } });
            } else if (inherited !== kind) {
                context.report({
                    node,
                    messageId: 'inheritedKindMismatch',
                    data: { kind, inherited: inherited ?? 'nothing' },
                });
            }
        }

        return {
            Program(program) {
                const { tests, describes } = collectTestCases(program, sourceCode.text, sourceCode.getAllComments());
                const baseline = options.baseline
                    ? (readBaseline(options.baseline)[baselineKey(options.baseline, context.filename)] ?? {})
                    : {};
                const remaining = { ...baseline };

                describes.forEach(checkDescribe);
                tests.forEach((test) => {
                    checkTest(test, {
                        // Each baselined identity covers as many untagged tests as it was counted.
                        consume() {
                            if (!(remaining[test.identity] > 0)) {
                                return false;
                            }

                            remaining[test.identity] -= 1;
                            return true;
                        },
                    });
                });
            },
        };
    },
};
