/**
 * @sw-package framework
 *
 * Finds component members for the upgrade codemods: the keys an options object declares, `this.<name>`
 * accesses and destructuring in scripts, and the free identifiers of template expressions.
 */

const OPTION_KINDS = ['computed', 'methods', 'watch', 'props', 'inject', 'provide', 'data', 'emits'];

/**
 * @param {object} property Property
 * @returns {string|null}
 */
function propertyName(property) {
    if (!property || property.type !== 'Property') {
        return null;
    }

    if (!property.computed && property.key.type === 'Identifier') {
        return property.key.name;
    }

    if (property.key.type === 'Literal') {
        return String(property.key.value);
    }

    return null;
}

/**
 * The object a `data()`/`provide()` function returns at its top level.
 *
 * @param {object} node function or object
 * @returns {object|null} ObjectExpression
 */
function returnedObject(node) {
    if (!node) {
        return null;
    }

    if (node.type === 'ObjectExpression') {
        return node;
    }

    if (node.type !== 'FunctionExpression' && node.type !== 'ArrowFunctionExpression') {
        return null;
    }

    if (node.body.type === 'ObjectExpression') {
        return node.body;
    }

    if (node.body.type !== 'BlockStatement') {
        return null;
    }

    const statement = node.body.body.find(
        (candidate) => candidate.type === 'ReturnStatement' && candidate.argument?.type === 'ObjectExpression',
    );

    return statement ? statement.argument : null;
}

/**
 * The members an options object declares.
 *
 * @param {object} options ObjectExpression
 * @returns {Array<{ kind: string, name: string, node: object }>} `node` is the declaring Property or array element
 */
function declaredMembers(options) {
    const members = [];

    for (const property of options.properties) {
        const kind = propertyName(property);

        if (!OPTION_KINDS.includes(kind)) {
            continue;
        }

        const value = kind === 'data' || kind === 'provide' ? returnedObject(property.value) : property.value;

        if (!value) {
            continue;
        }

        if (value.type === 'ArrayExpression') {
            for (const element of value.elements) {
                if (element && element.type === 'Literal' && typeof element.value === 'string') {
                    members.push({ kind, name: element.value, node: element });
                }
            }
        } else if (value.type === 'ObjectExpression') {
            for (const member of value.properties) {
                const name = propertyName(member);

                if (name) {
                    members.push({ kind, name, node: member });
                }
            }
        }
    }

    return members;
}

/**
 * The member name of `this.<name>` or `this['<name>']`.
 *
 * @param {object} node MemberExpression
 * @returns {string|null}
 */
function thisMemberName(node) {
    if (node.type !== 'MemberExpression' || node.object.type !== 'ThisExpression') {
        return null;
    }

    if (!node.computed && node.property.type === 'Identifier') {
        return node.property.name;
    }

    if (node.computed && node.property.type === 'Literal' && typeof node.property.value === 'string') {
        return node.property.value;
    }

    return null;
}

/**
 * The names a `const { a, b } = this` declaration reads.
 *
 * @param {object} node VariableDeclarator
 * @returns {Array<{ name: string, node: object }>}
 */
function destructuredThisMembers(node) {
    if (node.type !== 'VariableDeclarator' || node.init?.type !== 'ThisExpression' || node.id.type !== 'ObjectPattern') {
        return [];
    }

    return node.id.properties
        .map((property) => ({ name: propertyName(property), node: property }))
        .filter(({ name }) => name !== null);
}

/**
 * The identifiers a template expression reads from the component instance, without `v-for` and slot variables.
 *
 * @param {object} container VExpressionContainer
 * @returns {object[]} Identifier nodes
 */
function templateMemberReferences(container) {
    return (container.references ?? []).filter((reference) => !reference.variable).map((reference) => reference.id);
}

module.exports = {
    propertyName,
    declaredMembers,
    thisMemberName,
    destructuredThisMembers,
    templateMemberReferences,
};
