/**
 * @sw-package framework
 *
 * Writes the `TODO Codemod(<version>, <id>): …` comments of the upgrade codemods.
 *
 * The comment goes on its own line directly above the finding, with the finding's indentation:
 * `// …` in scripts, `{# … #}` in `.html.twig` templates and `<!-- … -->` in `.vue` templates.
 *
 * A finding whose TODO is already in the comment block above it is reported without a fix, so a second run
 * changes nothing and the upgrade report lists the spot under "Needs manual work".
 */

const TODO_PREFIX = 'TODO Codemod';

/**
 * @param {string} id
 * @param {string} [version]
 * @returns {string}
 */
function todoTag(id, version = '6.8') {
    return `${TODO_PREFIX}(${version}, ${id})`;
}

/**
 * @param {import('eslint').Rule.RuleContext} context
 * @returns {boolean}
 */
function isTwigFile(context) {
    const filename = context.physicalFilename ?? context.filename ?? '';

    return /\.twig$/.test(filename);
}

function lineStartIndex(text, index) {
    return text.lastIndexOf('\n', index - 1) + 1;
}

function indentationAt(text, lineStart) {
    return /^[ \t]*/.exec(text.slice(lineStart))[0];
}

/**
 * The indentation of the line that contains `index`.
 *
 * @param {string} text
 * @param {number} index
 * @returns {string}
 */
function lineIndentation(text, index) {
    return indentationAt(text, lineStartIndex(text, index));
}

/**
 * The comment lines directly above the line that contains `index`, nearest first.
 */
function commentLinesAbove(text, index) {
    const lines = [];
    let lineStart = lineStartIndex(text, index);

    while (lineStart > 0) {
        const previousStart = lineStartIndex(text, lineStart - 1);
        const line = text.slice(previousStart, lineStart - 1).trim();

        if (!/^(\/\/|\{#|<!--|\/\*|\*)/.test(line)) {
            break;
        }

        lines.push(line);
        lineStart = previousStart;
    }

    return lines;
}

/**
 * @param {'script'|'twig'|'vue'} syntax
 * @param {string} text
 * @returns {string}
 */
function wrapComment(syntax, text) {
    if (syntax === 'script') {
        return `// ${text.replace(/\r?\n/g, ' ')}`;
    }

    if (syntax === 'twig') {
        return `{# ${text.replace(/#\}/g, '# }')} #}`;
    }

    return `<!-- ${text.replace(/--/g, '- -')} -->`;
}

/**
 * Whether inserting a line at `lineStart` would land inside a multi-line string, template literal or comment.
 *
 * @param {import('eslint').SourceCode} sourceCode
 * @param {number} lineStart
 */
function splitsScriptToken(sourceCode, lineStart) {
    if (lineStart === 0 || typeof sourceCode.getNodeByRangeIndex !== 'function') {
        return false;
    }

    const node = sourceCode.getNodeByRangeIndex(lineStart);

    const stringTypes = ['TemplateLiteral', 'TemplateElement', 'Literal'];

    if (node && stringTypes.includes(node.type) && node.range[0] < lineStart) {
        return true;
    }

    return sourceCode.getAllComments().some((comment) => comment.range[0] < lineStart && comment.range[1] > lineStart);
}

/**
 * Reports a finding and fixes it by writing a TODO comment above it.
 *
 * @param {import('eslint').Rule.RuleContext} context
 * @param {object} options
 * @param {string} options.id Registry id of the codemod, part of the TODO tag.
 * @param {string} options.message The text of the report and of the TODO.
 * @param {[number, number]} options.range Range of the finding in the linted source.
 * @param {'script'|'template'} options.scope Where the finding is: script code or a template.
 * @param {string} [options.version]
 * @param {Set<string>} [options.seen] Findings already reported in this file, to report one TODO per line and text.
 */
function reportTodo(context, { id, message, range, scope, version = '6.8', seen }) {
    const sourceCode = context.sourceCode;
    const text = sourceCode.text;
    const lineStart = lineStartIndex(text, range[0]);
    const comment = `${todoTag(id, version)}: ${message}`;
    const loc = { start: sourceCode.getLocFromIndex(range[0]), end: sourceCode.getLocFromIndex(range[1]) };
    const key = `${lineStart}:${message}`;

    if (seen) {
        if (seen.has(key)) {
            return;
        }

        seen.add(key);
    }

    const alreadyMarked = commentLinesAbove(text, range[0]).some((line) => line.includes(comment));

    if (alreadyMarked || (scope === 'script' && splitsScriptToken(sourceCode, lineStart))) {
        context.report({ loc, message });

        return;
    }

    let syntax = 'script';

    if (scope === 'template') {
        syntax = isTwigFile(context) ? 'twig' : 'vue';
    }

    const line = `${indentationAt(text, lineStart)}${wrapComment(syntax, comment)}\n`;

    context.report({
        loc,
        message,
        fix: (fixer) => fixer.insertTextBeforeRange([lineStart, lineStart], line),
    });
}

module.exports = {
    todoTag,
    isTwigFile,
    wrapComment,
    lineIndentation,
    reportTodo,
};
