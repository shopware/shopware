/**
 * @sw-package framework
 */

const REVIEW_MARKER = 'TODO Codemod';

/**
 * Builds the review comment a codemod leaves in a template. `no-warning-comments` turns every
 * leftover into a lint error, so each one gets reviewed and removed.
 *
 * @example
 * templateCodemodComment('Check the icon'); // the HTML review comment that carries 'Check the icon'
 */
function templateCodemodComment(text) {
    return `<!-- ${REVIEW_MARKER}: ${text} -->`;
}

/**
 * Script counterpart of `templateCodemodComment`, for fixes in JavaScript expressions.
 *
 * @example
 * scriptCodemodComment('Check the plural'); // the block review comment that carries 'Check the plural'
 */
function scriptCodemodComment(text) {
    return `/* ${REVIEW_MARKER}: ${text} */`;
}

/**
 * The text to insert before `node` so that `comment` directly precedes it: on its own line with the
 * indentation of the node's line, tabs included, or in front of the node when other content shares
 * its line, so the line is not split.
 *
 * @example
 * commentBefore(sourceCode, iconNode, '<!-- x -->'); // '<!-- x -->\n\t\t' or '<!-- x --> '
 */
function commentBefore(sourceCode, node, comment) {
    const textBefore = sourceCode.lines[node.loc.start.line - 1].slice(0, node.loc.start.column);

    return textBefore.trim() === '' ? `${comment}\n${textBefore}` : `${comment} `;
}

/**
 * Puts a template review comment directly above `node`, see `commentBefore`.
 *
 * @example
 * insertTemplateCodemodComment(fixer, sourceCode, slotNode, 'Move this content');
 */
function insertTemplateCodemodComment(fixer, sourceCode, node, text) {
    return fixer.insertTextBefore(node, commentBefore(sourceCode, node, templateCodemodComment(text)));
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
    REVIEW_MARKER,
    commentBefore,
    convertedFromText,
    getLeadingComments,
    hasCodemodComment,
    insertTemplateCodemodComment,
    isConvertedFrom,
    scriptCodemodComment,
    templateCodemodComment,
};
