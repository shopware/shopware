/**
 * @sw-package framework
 *
 * AST helpers of the API usage rules (`utils/api-usage-rule.js`): how a usage is used, e.g. called,
 * written or compared with null, and how its replacement is written back.
 */

const espree = require('espree');
const { propertyName, staticString, unwrapTypeScript } = require('../registry/component-scope');

// Arguments that need no parentheses in front of a member access.
const PLAIN_ARGUMENT_TYPES = new Set([
    'Identifier',
    'MemberExpression',
    'CallExpression',
    'ThisExpression',
]);

// Replacements that need no parentheses wherever the replaced call stood.
const PLAIN_REPLACEMENT_TYPES = new Set([
    ...PLAIN_ARGUMENT_TYPES,
    'ChainExpression',
    'Literal',
    'TemplateLiteral',
    'ArrayExpression',
]);

const TYPESCRIPT_WRAPPERS = new Set([
    'TSAsExpression',
    'TSSatisfiesExpression',
    'TSNonNullExpression',
    'TSTypeAssertion',
]);

/**
 * Wraps a replacement in parentheses unless it is a member chain, call or literal, which can stand
 * wherever the replaced expression stood.
 *
 * @example
 * asExpression("Shopware.Filter.getByName('date')"); // unchanged
 * asExpression('a || b'); // '(a || b)'
 */
function asExpression(replacement) {
    try {
        const program = espree.parse(`(${replacement})`, { ecmaVersion: 'latest', sourceType: 'module' });
        const expression = program.body[0]?.expression;

        return PLAIN_REPLACEMENT_TYPES.has(expression?.type) ? replacement : `(${replacement})`;
    } catch {
        return `(${replacement})`;
    }
}

/**
 * The source of a call argument where it is followed by a member access: in parentheses unless it is
 * a plain reference or call.
 *
 * @example
 * argumentSource(sourceCode, node); // 'this.order' or '(order || fallback)'
 */
function argumentSource(sourceCode, argument) {
    const text = sourceCode.text.slice(argument.range[0], argument.range[1]);

    return PLAIN_ARGUMENT_TYPES.has(argument.type) ? text : `(${text})`;
}

/**
 * The node an expression is used as: climbs over TypeScript wrappers like `!` and `as`.
 */
function outerExpression(node) {
    let current = node;

    while (current.parent && TYPESCRIPT_WRAPPERS.has(current.parent.type) && current.parent.expression === current) {
        current = current.parent;
    }

    return current;
}

/**
 * The call that `node` is the callee of, or null.
 *
 * @example
 * callOf(memberOf('this.getDelivery(order)')); // the call expression
 */
function callOf(node) {
    const callee = outerExpression(node);

    return callee.parent?.type === 'CallExpression' && callee.parent.callee === callee ? callee.parent : null;
}

function isNullish(node) {
    const value = unwrapTypeScript(node);

    return (
        (value?.type === 'Literal' && value.value === null && !value.regex) ||
        (value?.type === 'Identifier' && value.name === 'undefined') ||
        (value?.type === 'UnaryExpression' && value.operator === 'void')
    );
}

/**
 * Whether a call result is compared with `null` or `undefined` by `===` or `!==`, which a
 * replacement that can be `undefined` instead of `null` would change.
 */
function isComparedStrictlyWithNullish(call) {
    const node = outerExpression(call);
    const parent = node.parent;

    if (
        parent?.type !== 'BinaryExpression' ||
        ![
            '===',
            '!==',
        ].includes(parent.operator)
    ) {
        return false;
    }

    return isNullish(parent.left === node ? parent.right : parent.left);
}

/**
 * Whether the call of `node` leaves out the argument at `index`, or passes null or undefined for it.
 * A spread argument up to that index may pass it, so it counts as passed.
 *
 * @example
 * leavesOutArgument(memberOf("mailService.sendMailTemplate(a, b)"), 2); // true
 */
function leavesOutArgument(node, index) {
    const call = callOf(node);

    if (!call || call.arguments.slice(0, index + 1).some((argument) => argument.type === 'SpreadElement')) {
        return false;
    }

    return call.arguments.length <= index || isNullish(call.arguments[index]);
}

/**
 * Whether `node` is the target of an assignment or update, e.g. `this.isLoading = true`.
 */
function isWritten(node) {
    const outer = outerExpression(node);
    const parent = outer.parent;

    return (
        (parent?.type === 'AssignmentExpression' && parent.left === outer) ||
        (parent?.type === 'UpdateExpression' && parent.argument === outer)
    );
}

/**
 * Whether a string literal is a value in the code, not an object key or a module path.
 */
function isStringValue(node) {
    const parent = node.parent;

    if (
        [
            'Property',
            'MethodDefinition',
            'PropertyDefinition',
            'TSPropertySignature',
            'TSEnumMember',
            'ImportAttribute',
        ].includes(parent?.type) &&
        (parent.key === node || parent.id === node)
    ) {
        return false;
    }

    if (
        [
            'ImportDeclaration',
            'ExportNamedDeclaration',
            'ExportAllDeclaration',
            'ImportExpression',
            'TSImportType',
            'TSExternalModuleReference',
        ].includes(parent?.type)
    ) {
        return false;
    }

    return !(
        parent?.type === 'CallExpression' &&
        parent.callee.type === 'Identifier' &&
        parent.callee.name === 'require' &&
        parent.arguments[0] === node
    );
}

/**
 * The names an `import()` or `require()` result is destructured into, or null when they are unknown.
 *
 * @example
 * destructuredNames(callOf("const { CancelToken } = require('axios')")); // ['CancelToken']
 */
function destructuredNames(node) {
    let current = node;

    if (current.parent?.type === 'AwaitExpression') {
        current = current.parent;
    }

    const declarator = current.parent;

    if (declarator?.type !== 'VariableDeclarator' || declarator.init !== current || declarator.id.type !== 'ObjectPattern') {
        return null;
    }

    return declarator.id.properties.map((property) => (property.type === 'Property' ? propertyName(property) : null)).filter(Boolean);
}

/**
 * The names an import or re-export declaration takes from its module at runtime: `default` for a
 * default import, `*` for a namespace import. Null when the declaration only takes types.
 *
 * @example
 * importedNames(parse("import axios, { CancelToken } from 'axios'")); // ['default', 'CancelToken']
 */
function importedNames(declaration) {
    if (declaration.importKind === 'type' || declaration.exportKind === 'type') {
        return null;
    }

    const specifiers = declaration.specifiers ?? [];
    const runtimeSpecifiers = specifiers.filter((specifier) => {
        return specifier.importKind !== 'type' && specifier.exportKind !== 'type';
    });

    // A declaration of type-only specifiers needs nothing of the module at runtime.
    if (specifiers.length > 0 && runtimeSpecifiers.length === 0) {
        return null;
    }

    return runtimeSpecifiers.map((specifier) => {
        if (specifier.type === 'ImportDefaultSpecifier') {
            return 'default';
        }

        if (specifier.type === 'ImportNamespaceSpecifier') {
            return '*';
        }

        const imported = specifier.type === 'ExportSpecifier' ? specifier.local : specifier.imported;

        return imported.name ?? imported.value;
    });
}

/**
 * The key a property of an `inject` object injects: `{ local: 'key' }`, `{ local: { from: 'key' } }`
 * or `{ key: { default: … } }`. Null for keys that are no string, such as symbols.
 */
function injectedKey(property) {
    const value = unwrapTypeScript(property.value);
    const key = staticString(value);

    if (key !== null) {
        return key;
    }

    if (value?.type !== 'ObjectExpression') {
        return null;
    }

    const from = value.properties.find((candidate) => candidate.type === 'Property' && propertyName(candidate) === 'from');

    return from ? staticString(from.value) : propertyName(property);
}

/**
 * Whether a module path is one of the sources, as a whole or as the end after a `/`.
 *
 * @example
 * matchesImportSource('src/core/factory/http.factory', ['src/core/factory/http.factory']); // true
 */
function matchesImportSource(source, sources) {
    return typeof source === 'string' && sources.some((candidate) => source === candidate || source.endsWith(`/${candidate}`));
}

module.exports = {
    argumentSource,
    asExpression,
    callOf,
    destructuredNames,
    importedNames,
    injectedKey,
    isComparedStrictlyWithNullish,
    isStringValue,
    isWritten,
    leavesOutArgument,
    matchesImportSource,
};
