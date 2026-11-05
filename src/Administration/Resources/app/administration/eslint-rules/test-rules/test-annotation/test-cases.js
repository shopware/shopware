/**
 * @sw-package framework
 */

/**
 * Finds the `it` and `describe` calls of a spec and the doc comments above them.
 * Shared by the `test-annotation` rules and the baseline script, so both agree on what a test is and how it is named.
 *
 * @example
 * const { tests, describes } = collectTestCases(ast, sourceText, ast.comments);
 */

const SKIPPED_KEYS = new Set([
    'parent',
    'loc',
    'range',
    'tokens',
    'comments',
]);

/**
 * Returns the identifier a call chain starts with:
 * `it`, `it.each(rows)`, `it.activeFeatureFlags([...]).each(rows)` and `it.deprecated('v6.8.0.0')` all start with `it`.
 */
function calleeRoot(callee) {
    let node = callee;

    while (node) {
        if (node.type === 'Identifier') {
            return node.name;
        }

        if (node.type === 'MemberExpression') {
            node = node.object;
        } else if (node.type === 'CallExpression') {
            node = node.callee;
        } else {
            return null;
        }
    }

    return null;
}

/**
 * Whether a call argument is an inline callback, which tells a registered test from a helper call.
 */
function isFunction(node) {
    return node?.type === 'ArrowFunctionExpression' || node?.type === 'FunctionExpression';
}

/**
 * Only the call that registers the callback counts, so `it.activeFeatureFlags([...])` alone is not a test,
 * and `it.todo(name)` has nothing to annotate.
 */
function kindOfCall(node) {
    if (node.type !== 'CallExpression' || !isFunction(node.arguments[1])) {
        return null;
    }

    const root = calleeRoot(node.callee);

    if (root === 'it') {
        return 'test';
    }

    return root === 'describe' ? 'describe' : null;
}

/**
 * The title as written in the source, so `it.each` placeholders and template literals stay stable.
 */
function titleOf(node, sourceText) {
    const title = node.arguments[0];

    if (!title) {
        return '';
    }

    if (title.type === 'Literal' && typeof title.value === 'string') {
        return title.value;
    }

    return sourceText.slice(title.range[0], title.range[1]);
}

/**
 * Splits a doc comment into its free text and its tags.
 * A tag's value continues on the following lines until the next tag.
 *
 * @example
 * parseDocComment('*\n * Rows got lost.\n * @regression\n * @see https://github.com/shopware/shopware/issues/1\n ')
 * // { description: 'Rows got lost.', tags: [{ name: 'regression', value: '' }, { name: 'see', value: 'https://…/1' }] }
 */
function parseDocComment(commentValue) {
    const lines = commentValue.split('\n').map((line) => line.replace(/^\s*\*?\s?/, '').trimEnd());
    const description = [];
    const tags = [];

    lines.forEach((line) => {
        const tag = line.match(/^@([\w-]+)\s*(.*)$/);

        if (tag) {
            tags.push({ name: tag[1], value: tag[2].trim() });
        } else if (tags.length > 0) {
            const last = tags[tags.length - 1];
            last.value = `${last.value} ${line.trim()}`.trim();
        } else if (line.trim() !== '') {
            description.push(line.trim());
        }
    });

    return { description: description.join(' '), tags };
}

/**
 * The `/** … *\/` comment that directly precedes the statement of a call, or null.
 */
function docCommentOf(node, sourceText, comments) {
    const anchor = node.parent?.type === 'ExpressionStatement' && node.parent.expression === node ? node.parent : node;
    const start = anchor.range[0];
    let found = null;

    comments.forEach((comment) => {
        if (
            comment.type === 'Block' &&
            comment.value.startsWith('*') &&
            comment.range[1] <= start &&
            sourceText.slice(comment.range[1], start).trim() === ''
        ) {
            found = comment;
        }
    });

    return found ? { comment: found, ...parseDocComment(found.value) } : null;
}

/**
 * Walks the program, setting `parent` where a parser left it out, and calls `visit` for every node.
 */
function walk(node, parent, visit) {
    if (!node || typeof node.type !== 'string') {
        return;
    }

    if (!node.parent && parent) {
        node.parent = parent;
    }

    visit(node);

    Object.keys(node).forEach((key) => {
        if (SKIPPED_KEYS.has(key)) {
            return;
        }

        const value = node[key];

        if (Array.isArray(value)) {
            value.forEach((child) => walk(child, node, visit));
        } else if (value && typeof value.type === 'string') {
            walk(value, node, visit);
        }
    });
}

/**
 * Every test and describe of a program, each with its doc comment.
 * A test lists its enclosing describes, nearest first, and has an identity that names it in the baseline.
 *
 * @example
 * const { tests } = collectTestCases(ast, text, comments);
 * tests[0].identity; // 'sw-foo > selection > should keep selected rows'
 */
function collectTestCases(program, sourceText, comments) {
    const describes = new Map();
    const tests = [];

    walk(program, null, (node) => {
        const kind = kindOfCall(node);

        if (!kind) {
            return;
        }

        const entry = {
            node,
            title: titleOf(node, sourceText),
            doc: docCommentOf(node, sourceText, comments),
            describes: [],
        };

        for (let ancestor = node.parent; ancestor; ancestor = ancestor.parent) {
            if (describes.has(ancestor)) {
                entry.describes.push(describes.get(ancestor));
            }
        }

        if (kind === 'describe') {
            describes.set(node, entry);
            return;
        }

        entry.identity = [
            ...entry.describes.map((describe) => describe.title).reverse(),
            entry.title,
        ].join(' > ');
        tests.push(entry);
    });

    return { tests, describes: [...describes.values()] };
}

module.exports = {
    collectTestCases,
};
