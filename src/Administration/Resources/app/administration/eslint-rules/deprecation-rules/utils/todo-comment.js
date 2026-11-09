/**
 * @sw-package framework
 *
 * Writes `TODO Codemod:` comments for the upgrade codemods that cannot rewrite a removed API
 * safely. Every helper reports the spot on each run, but only adds the fix while the comment
 * is still missing, so a second run lists the open spots without writing the comment twice.
 *
 * Reports use the message id `todo`: `admin:upgrade-extensions` lists every such report as a
 * spot that needs manual work, whether or not it still carries the fix.
 */

const TODO_PREFIX = 'TODO Codemod:';
const TODO_MESSAGE_ID = 'todo';

const todoMessages = {
    [TODO_MESSAGE_ID]: '{{ hint }}',
};

/**
 * @param {string} text
 * @param {number} index
 * @returns {{ start: number, indentation: string }}
 */
function lineAt(text, index) {
    const start = text.lastIndexOf('\n', index - 1) + 1;
    const indentation = /^[ \t]*/.exec(text.slice(start))[0];

    return { start, indentation };
}

/**
 * Whether one of the comment lines directly above the line at `lineStart` already holds the hint.
 *
 * @param {string} text
 * @param {number} lineStart
 * @param {string} hint
 */
function hasTodoAbove(text, lineStart, hint) {
    const lines = text.slice(0, Math.max(lineStart - 1, 0)).split('\n').reverse();

    for (const line of lines) {
        const trimmed = line.trim();

        // A spot that was in the middle of a line sits below the line that ends with its comment.
        if (trimmed.includes(`${TODO_PREFIX} ${hint}`)) {
            return true;
        }

        if (!/^(\/\/|\/\*|\*|<!--|\{#)/.test(trimmed)) {
            return false;
        }
    }

    return false;
}

/**
 * Remembers which hint was reported at which offset, so two matches on one line report once.
 *
 * @param {import('eslint').Rule.RuleContext} context
 */
function createTodoReporter(context) {
    const sourceCode = context.sourceCode;
    const text = sourceCode.text;
    const reported = new Set();

    /**
     * @param {number} anchor offset where the comment line goes
     * @param {string} hint
     * @param {(hint: string) => string} format
     */
    function report(anchor, hint, format, onlyIfWritten = false) {
        const key = `${anchor}:${hint}`;

        if (reported.has(key)) {
            return;
        }

        const { start, indentation } = lineAt(text, anchor);
        const firstOnLine = text.slice(start, anchor).trim() === '';
        const commentLineStart = firstOnLine ? start : anchor;
        const alreadyWritten = firstOnLine && hasTodoAbove(text, commentLineStart, hint);

        if (onlyIfWritten && !alreadyWritten) {
            return;
        }

        reported.add(key);

        context.report({
            loc: sourceCode.getLocFromIndex(anchor),
            messageId: TODO_MESSAGE_ID,
            data: { hint },
            fix: alreadyWritten
                ? null
                : (fixer) => {
                    // A spot in the middle of a line moves to its own line below the comment.
                    const prefix = firstOnLine ? indentation : '';
                    const insertAt = firstOnLine ? start : anchor;

                    return fixer.insertTextBeforeRange(
                        [insertAt, insertAt],
                        `${prefix}${format(`${TODO_PREFIX} ${hint}`)}\n${firstOnLine ? '' : indentation}`,
                    );
                },
        });
    }

    /**
     * Puts a `// TODO Codemod:` line above the line of a script node.
     *
     * @param {import('eslint').Rule.Node} node
     * @param {string} hint
     */
    function reportScript(node, hint) {
        report(scriptAnchor(sourceCode, node), hint, (comment) => `// ${comment}`);
    }

    /**
     * Puts an `<!-- TODO Codemod: -->` line above a template element.
     *
     * @param {{ range: [number, number] }} element
     * @param {string} hint
     */
    function reportTemplate(element, hint) {
        report(element.range[0], hint, (comment) => `<!-- ${comment} -->`);
    }

    /**
     * Keeps reporting a TODO comment an earlier fix wrote above an element it also rewrote,
     * e.g. a renamed tag that still needs a manual change.
     *
     * @param {{ range: [number, number] }} element
     * @param {string} hint
     */
    function reportTemplateIfWritten(element, hint) {
        report(element.range[0], hint, (comment) => `<!-- ${comment} -->`, true);
    }

    /**
     * Puts a `{# TODO Codemod: #}` line above a Twig tag, e.g. a `{% block %}`.
     *
     * @param {number} offset
     * @param {string} hint
     */
    function reportTwig(offset, hint) {
        report(offset, hint, (comment) => `{# ${comment} #}`);
    }

    return { reportScript, reportTemplate, reportTemplateIfWritten, reportTwig };
}

/**
 * The offset of the first token on the line of `node`, so the comment never lands inside a
 * multi-line string or template literal. Climbs to the parent when that line starts inside one.
 *
 * @param {import('eslint').SourceCode} sourceCode
 * @param {import('eslint').Rule.Node} node
 */
function scriptAnchor(sourceCode, node) {
    const text = sourceCode.text;
    let current = node;

    while (current) {
        const { start, indentation } = lineAt(text, current.range[0]);
        const firstColumn = start + indentation.length;
        const token = sourceCode.getTokenByRangeStart(firstColumn, { includeComments: true });

        if (token) {
            return firstColumn;
        }

        current = current.parent;
    }

    return node.range[0];
}

module.exports = {
    todoMessages,
    createTodoReporter,
};
