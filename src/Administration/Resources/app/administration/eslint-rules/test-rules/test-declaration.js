/**
 * @sw-package framework
 */

/**
 * Returns true for the call that declares a test: `it(...)`, `it.only(...)`
 * and the curried helpers such as `it.deprecated('v6.8.0.0')(...)`.
 *
 * @param {import('estree').CallExpression} node
 * @returns {boolean}
 */
function isTestDeclaration(node) {
    const callee = node.callee;

    if (callee.type === 'Identifier') {
        return callee.name === 'it';
    }

    if (callee.type === 'MemberExpression') {
        return callee.object.type === 'Identifier' && callee.object.name === 'it' && node.parent.type !== 'CallExpression';
    }

    return (
        callee.type === 'CallExpression' && callee.callee.type === 'MemberExpression' && callee.callee.object.name === 'it'
    );
}

module.exports = { isTestDeclaration };
