/**
 * @sw-package framework
 */

/**
 * Builds the review comment a codemod leaves in a template. `no-warning-comments` turns every
 * leftover into a lint error, so each one gets reviewed and removed.
 *
 * @example
 * templateCodemodComment('Check the icon'); // the HTML review comment that carries 'Check the icon'
 */
function templateCodemodComment(text) {
    return `<!-- TODO Codemod: ${text} -->`;
}

/**
 * Script counterpart of `templateCodemodComment`, for fixes in JavaScript expressions.
 *
 * @example
 * scriptCodemodComment('Check the plural'); // the block review comment that carries 'Check the plural'
 */
function scriptCodemodComment(text) {
    return `/* TODO Codemod: ${text} */`;
}

/**
 * Puts a template review comment on its own line above `node`, indented like `node`.
 *
 * @example
 * insertTemplateCodemodComment(fixer, slotNode, 'Move this content'); // comment line, then the slot at its old indentation
 */
function insertTemplateCodemodComment(fixer, node, text) {
    return fixer.insertTextBefore(node, `${templateCodemodComment(text)}\n${' '.repeat(node.loc.start.column)}`);
}

/**
 * The text of the comment `no-deprecated-components` puts above every converted element. Follow-up
 * steps find converted elements by it, so producer and detector share this one definition.
 *
 * @example
 * convertedFromText('sw-icon'); // 'Converted from sw-icon - please check if everything works correctly'
 */
function convertedFromText(componentName) {
    return `Converted from ${componentName} - please check if everything works correctly`;
}

/**
 * Lists the comments directly above an element, nearest first, with only whitespace in between.
 *
 * @example
 * getLeadingComments(sourceCode, node); // the conversion comment right above a converted <mt-icon>
 */
function getLeadingComments(sourceCode, node) {
    const comments = sourceCode.ast?.templateBody?.comments ?? [];
    const leadingComments = [];
    let end = node.range[0];

    comments
        .filter((comment) => comment.range[1] <= node.range[0])
        .sort((left, right) => right.range[0] - left.range[0])
        .every((comment) => {
            if (sourceCode.text.slice(comment.range[1], end).trim() !== '') {
                return false;
            }

            leadingComments.push(comment);
            end = comment.range[0];

            return true;
        });

    return leadingComments;
}

/**
 * Tells whether `no-deprecated-components` converted the element from the given component.
 *
 * @example
 * isConvertedFrom(sourceCode, mtIconNode, 'sw-icon'); // true right after the sw-icon conversion
 */
function isConvertedFrom(sourceCode, node, componentName) {
    const marker = convertedFromText(componentName);

    return getLeadingComments(sourceCode, node).some((comment) => comment.value.includes(marker));
}

/**
 * Tells whether a comment containing `text` sits inside the element, so a review hint is added only once.
 *
 * @example
 * hasCodemodComment(sourceCode, node, 'The "content" slot is not used anymore.'); // true after the first fix
 */
function hasCodemodComment(sourceCode, node, text) {
    return (sourceCode.ast?.templateBody?.comments ?? []).some((comment) => {
        return (
            comment.value.includes(text) &&
            comment.loc.start.line >= node.loc.start.line &&
            comment.loc.end.line <= node.loc.end.line
        );
    });
}

module.exports = {
    convertedFromText,
    getLeadingComments,
    hasCodemodComment,
    insertTemplateCodemodComment,
    isConvertedFrom,
    scriptCodemodComment,
    templateCodemodComment,
};
