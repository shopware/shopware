/**
 * @sw-package framework
 */

const KINDS = [
    'regression',
    'behavior',
    'characterization',
];

/**
 * Returns the identifier a call chain starts with:
 * `it`, `it.each(rows)`, `it.activeFeatureFlags([...]).each(rows)` and `it.deprecated('v6.8.0.0')` all start with `it`.
 */
function calleeRoot(callee) {
    let node = callee;

    while (node?.type === 'MemberExpression' || node?.type === 'CallExpression') {
        node = node.type === 'MemberExpression' ? node.object : node.callee;
    }

    return node?.type === 'Identifier' ? node.name : null;
}

/**
 * Whether a call registers a test (`'it'`) or a group (`'describe'`), or null for any other call.
 * Only the call that receives the callback counts, so `it.activeFeatureFlags([...])` alone is not a test,
 * and `it.todo(name)` has nothing to annotate.
 */
function testCallKind(node) {
    const callback = node.arguments[1];

    if (callback?.type !== 'ArrowFunctionExpression' && callback?.type !== 'FunctionExpression') {
        return null;
    }

    const root = calleeRoot(node.callee);

    return root === 'it' || root === 'describe' ? root : null;
}

/**
 * The tags of a doc comment as `{ name, value }`.
 * A tag's value continues on the following lines until the next tag.
 */
function parseTags(commentValue) {
    const tags = [];

    commentValue.split('\n').forEach((rawLine) => {
        const line = rawLine.replace(/^\s*\*?\s?/, '').trim();
        const tag = line.match(/^@([\w-]+)\s*(.*)$/);

        if (tag) {
            tags.push({ name: tag[1], value: tag[2] });
        } else if (tags.length > 0 && line !== '') {
            tags[tags.length - 1].value = `${tags[tags.length - 1].value} ${line}`.trim();
        }
    });

    return tags;
}

/**
 * Reads the annotation from the `/** … *\/` comment directly above a test or describe call.
 * `see` holds the linked targets; `{@link url}` is read as its url.
 *
 * @example
 * annotationOf(itCall, sourceCode);
 * // { kinds: ['regression'], see: ['https://github.com/shopware/shopware/issues/1'], seeException: undefined }
 */
function annotationOf(node, sourceCode) {
    const comment = sourceCode.getCommentsBefore(node).at(-1);
    const isDocComment = comment?.type === 'Block' && comment.value.startsWith('*');
    const tags = isDocComment ? parseTags(comment.value) : [];
    const values = (name) => tags.filter((tag) => tag.name === name).map((tag) => tag.value);

    return {
        kinds: tags.map((tag) => tag.name).filter((name) => KINDS.includes(name)),
        see: values('see').map((value) => value.replace(/^\{@link\s+/, '').split(/[\s|}]/)[0]),
        seeException: values('seeException')[0],
    };
}

module.exports = {
    testCallKind,
    annotationOf,
};
