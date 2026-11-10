/**
 * @sw-package framework
 *
 * Reports `@deprecated` and `@bc-change` markers that are not well-formed, and near misses of both tag names.
 * A malformed marker would otherwise stay a plain comment, and the change it announces would surface as an
 * unannounced break. See `technical-docs/11-code-quality/03-public-api-snapshot.md`.
 */

const { createJiti } = require('jiti');

// The grammar is TypeScript so the public API generator can reuse it, jiti loads it into this CommonJS plugin.
const { parseMarkers, parseTemplateMarkers } = createJiti(__filename)('./utils/api-markers.ts');

// `//`, `/*` and a `#!` shebang all open with two characters
const COMMENT_OPENER_LENGTH = 2;

/** @type {import('eslint').Rule.RuleModule} */
module.exports = {
    meta: {
        type: 'problem',
        docs: {
            description: 'API markers have to follow the marker grammar',
            recommended: true,
        },
        schema: [],
    },
    create(context) {
        const sourceCode = context.sourceCode;

        function report(matches, textStart) {
            matches.forEach((match) => {
                const start = textStart + match.offset;

                (match.errors ?? []).forEach((message) => {
                    context.report({
                        loc: {
                            start: sourceCode.getLocFromIndex(start),
                            end: sourceCode.getLocFromIndex(start + match.length),
                        },
                        message,
                    });
                });
            });
        }

        return {
            Program() {
                sourceCode.getAllComments().forEach((comment) => {
                    report(parseMarkers(comment.value), comment.range[0] + COMMENT_OPENER_LENGTH);
                });

                // vue-eslint-parser keeps Twig comments as plain text, so they are only visible in the source text
                const template = sourceCode.ast.templateBody;

                if (template) {
                    report(parseTemplateMarkers(sourceCode.text.slice(...template.range)), template.range[0]);
                }
            },
        };
    },
};
