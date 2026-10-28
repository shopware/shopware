/**
 * @sw-package framework
 */

const fs = require('fs');
const path = require('path');

const TAG_PATTERN = /@deprecated\s+tag:v(\d+)\.(\d+)\.(\d+)(?:\.(\d+))?/;
const GUARD_NAME = 'triggerDeprecationOrThrow';

/**
 * Calls that wrap a method and run the wrapped function on every use, so the guard goes into the wrapped function.
 *
 * @example
 * onSearch: Utils.debounce(function onSearch() { … }, 300)
 */
const FUNCTION_WRAPPERS = ['debounce', 'throttle'];

/**
 * A deprecation that only takes something out of the public API, like "Will be private", keeps it working.
 *
 * @example
 * VISIBILITY_PATTERN.test('@deprecated tag:v6.8.0 - Will be private'); // true
 */
const VISIBILITY_PATTERN = /@deprecated\s+tag:\S+\s*-\s*will be @?private/i;

/**
 * Vue and store options whose functions run on behalf of core, not when an extension uses them, so a guard
 * there would fire for every instance instead of for the legacy use.
 *
 * @example
 * watch: { \/** @deprecated tag:v6.8.0 *\/ value() { … } } // no guard required
 */
const UNGUARDED_SECTIONS = [
    'data',
    'watch',
    'provide',
    'inject',
    'state',
    'getters',
];

/**
 * Options only a component config has, so `export default { … }` of a plain object is no component.
 *
 * @example
 * export default { template, props: { … } } // a component
 */
const COMPONENT_OPTIONS = [
    'template',
    'render',
    'setup',
    'props',
    'data',
    'computed',
    'methods',
    'watch',
    'inject',
    'emits',
    'mixins',
    'components',
    'created',
    'mounted',
];

/**
 * The major flag a `@deprecated tag:` announces, as `triggerDeprecationOrThrow` expects it.
 *
 * @example
 * toMajorFlag(TAG_PATTERN.exec('@deprecated tag:v6.8.0')); // 'V6_8_0_0'
 */
function toMajorFlag(tag) {
    return `V${[
        tag[1],
        tag[2],
        tag[3],
        tag[4] ?? '0',
    ].join('_')}`;
}

/**
 * The version the `deprecated` component or prop option takes for a `@deprecated tag:`.
 *
 * @example
 * toVersion(TAG_PATTERN.exec('@deprecated tag:v6.8.0')); // 'v6.8.0.0'
 */
function toVersion(tag) {
    return `v${tag[1]}.${tag[2]}.${tag[3]}.${tag[4] ?? '0'}`;
}

/**
 * The name of a property, method or variable, `''` when it has none.
 *
 * @example
 * keyName(node); // 'oldMethod'
 */
function keyName(node) {
    if (!node) {
        return '';
    }

    if (node.type === 'Identifier') {
        return node.name;
    }

    if (node.type === 'Literal') {
        return String(node.value);
    }

    return '';
}

/**
 * The name a report uses for the deprecated declaration.
 *
 * @example
 * declarationName(propertyNode); // 'oldMethod'
 */
function declarationName(node) {
    switch (node.type) {
        case 'Property':
        case 'MethodDefinition':
        case 'PropertyDefinition':
            return keyName(node.key);
        case 'FunctionDeclaration':
        case 'ClassDeclaration':
            return keyName(node.id);
        case 'VariableDeclaration':
            return keyName(node.declarations[0]?.id);
        case 'ExpressionStatement':
            return node.expression.type === 'AssignmentExpression' && node.expression.left.type === 'MemberExpression'
                ? keyName(node.expression.left.property)
                : '';
        default:
            return '';
    }
}

/**
 * The Vue option a property is a direct member of: `methods` for `methods: { oldMethod() {} }`.
 *
 * @example
 * sectionOf(oldMethodProperty); // 'methods'
 */
function sectionOf(property) {
    const object = property.parent;

    if (object?.type !== 'ObjectExpression') {
        return '';
    }

    if (object.parent?.type === 'Property' && object.parent.value === object) {
        return keyName(object.parent.key);
    }

    // `data() { return { … } }`, `provide() { return { … } }` and the arrow form `state: () => ({ … })`
    const isReturned =
        object.parent?.type === 'ReturnStatement' ||
        (object.parent?.type === 'ArrowFunctionExpression' && object.parent.body === object);

    if (!isReturned) {
        return '';
    }

    let current = object.parent;

    while (current && current.type !== 'Property') {
        current = current.parent;
    }

    return current ? keyName(current.key) : '';
}

/**
 * The declarations a file exports, with `export { x }` and `export default x` resolved to the declaration of `x`.
 *
 * @example
 * exportedDeclarations(program); // [FunctionDeclaration a, FunctionDeclaration b]
 */
function exportedDeclarations(program) {
    const topLevel = new Map();

    program.body.forEach((statement) => {
        const declaration = statement.type.startsWith('Export') ? statement.declaration : statement;

        if (declaration?.type === 'VariableDeclaration') {
            topLevel.set(keyName(declaration.declarations[0]?.id), declaration);
        } else if (declaration?.id) {
            topLevel.set(keyName(declaration.id), declaration);
        }
    });

    return program.body
        .flatMap((statement) => {
            if (statement.type === 'ExportNamedDeclaration') {
                if (statement.declaration) {
                    return [statement.declaration];
                }

                return statement.source
                    ? []
                    : statement.specifiers.map((specifier) => topLevel.get(keyName(specifier.local)));
            }

            if (statement.type === 'ExportDefaultDeclaration') {
                return [
                    statement.declaration.type === 'Identifier'
                        ? topLevel.get(statement.declaration.name)
                        : statement.declaration,
                ];
            }

            return [];
        })
        .filter(Boolean);
}

/**
 * The file a `Component.register('sw-x', () => import('./sw-x'))` or `Component.extend('sw-x', 'sw-base', …)`
 * call registers, or `null` for any other statement.
 *
 * @example
 * registeredFile(statement, '/admin/src/module/sw-order/index.js'); // '/admin/src/module/sw-order/sw-x/index.js'
 */
function registeredFile(node, filename) {
    const call = node.type === 'ExpressionStatement' ? node.expression : null;
    const callee = call?.type === 'CallExpression' ? call.callee : null;

    if (callee?.type !== 'MemberExpression' || !['register', 'extend'].includes(keyName(callee.property))) {
        return null;
    }

    const loader = call.arguments[call.arguments.length - 1];
    const importExpression = isFunction(loader) ? loader.body : null;
    const source = importExpression?.type === 'ImportExpression' ? importExpression.source.value : null;

    if (typeof source !== 'string' || !source.startsWith('.')) {
        return null;
    }

    const base = path.resolve(path.dirname(filename), source);

    return (
        [
            '',
            '.js',
            '.ts',
            '/index.js',
            '/index.ts',
        ]
            .map((extension) => `${base}${extension}`)
            .find((candidate) => fs.existsSync(candidate) && fs.statSync(candidate).isFile()) ?? null
    );
}

/**
 * Whether a component file has a root `deprecated` option, the indentation of a root option being four spaces.
 *
 * @example
 * hasRootDeprecatedOption('/admin/src/module/sw-order/sw-x/index.js'); // true
 */
function hasRootDeprecatedOption(file) {
    return /^ {4}deprecated: \{/m.test(fs.readFileSync(file, 'utf8'));
}

/**
 * The component config of `export default { … }` and `export default Component.wrapComponentConfig({ … })`.
 *
 * @example
 * componentConfigOf(exportDefaultNode); // ObjectExpression
 */
function componentConfigOf(node) {
    if (node.type !== 'ExportDefaultDeclaration') {
        return null;
    }

    const declaration = node.declaration;
    const config =
        declaration.type === 'CallExpression' && declaration.arguments[0]?.type === 'ObjectExpression'
            ? declaration.arguments[0]
            : declaration;

    if (config.type !== 'ObjectExpression') {
        return null;
    }

    const isComponent = config.properties.some(
        (property) => property.type === 'Property' && COMPONENT_OPTIONS.includes(keyName(property.key)),
    );

    return isComponent ? config : null;
}

/**
 * Whether an object literal has a `deprecated` option, as the deprecation plugin reads it from components and props.
 *
 * @example
 * hasDeprecatedOption(propDefinition); // true for { type: String, deprecated: { … } }
 */
function hasDeprecatedOption(object) {
    return deprecatedOption(object) !== null;
}

/**
 * The `deprecated` option of a component or prop definition, or `null` when it has none.
 *
 * @example
 * deprecatedOption(propDefinition); // the ObjectExpression `{ version: 'v6.8.0.0', comment: '…' }`
 */
function deprecatedOption(object) {
    if (object?.type !== 'ObjectExpression') {
        return null;
    }

    const option = object.properties.find(
        (property) => property.type === 'Property' && keyName(property.key) === 'deprecated',
    );

    return option?.value ?? null;
}

/**
 * Whether the `version` of a `deprecated` option names the version the tag announces. A version that is no string
 * literal cannot be compared and passes.
 *
 * @example
 * versionMatches(option, TAG_PATTERN.exec('@deprecated tag:v6.8.0')); // true for { version: 'v6.8.0.0' }
 */
function versionMatches(option, tag) {
    const version =
        option?.type === 'ObjectExpression'
            ? option.properties.find((property) => property.type === 'Property' && keyName(property.key) === 'version')
                  ?.value
            : null;

    if (version?.type !== 'Literal' || typeof version.value !== 'string') {
        return true;
    }

    const segments = version.value.replace(/^v/i, '').split('.');

    return (
        toVersion(tag) ===
        `v${[
            0,
            1,
            2,
            3,
        ]
            .map((index) => segments[index] ?? '0')
            .join('.')}`
    );
}

/**
 * `value` without the TypeScript assertions wrapped around it.
 *
 * @example
 * unwrap(node); // the function of `function () {} as typeof x`
 */
function unwrap(value) {
    let current = value;

    while (current && ['TSAsExpression', 'TSSatisfiesExpression', 'TSNonNullExpression'].includes(current.type)) {
        current = current.expression;
    }

    return current;
}

function isFunction(node) {
    return ['FunctionExpression', 'ArrowFunctionExpression', 'FunctionDeclaration'].includes(node?.type);
}

/**
 * The functions whose first statement has to be the guard, or `null` when the declaration is no function at all.
 * A computed written as an accessor object contributes both `get` and `set`, a class its constructor, and a
 * wrapped function like `Utils.debounce(function () { … })` the function it wraps.
 *
 * @example
 * guardedFunctions(oldMethodProperty); // [FunctionExpression]
 */
function guardedFunctions(node) {
    let value;

    switch (node.type) {
        case 'FunctionDeclaration':
        case 'FunctionExpression':
        case 'ArrowFunctionExpression':
        case 'ClassDeclaration':
        case 'ClassExpression':
            value = node;
            break;
        case 'Property':
        case 'MethodDefinition':
        case 'PropertyDefinition':
            value = node.value;
            break;
        case 'VariableDeclaration':
            value = node.declarations[0]?.init;
            break;
        case 'ExpressionStatement':
            value = node.expression.type === 'AssignmentExpression' ? node.expression.right : null;
            break;
        default:
            return null;
    }

    value = unwrap(value);

    if (isFunction(value)) {
        return [value];
    }

    if (value?.type === 'ClassDeclaration' || value?.type === 'ClassExpression') {
        const constructor = value.body.body.find(
            (member) => member.type === 'MethodDefinition' && member.kind === 'constructor',
        );

        if (constructor && leadingGuardFlag(constructor.value) !== null) {
            return [constructor.value];
        }

        // A class core creates on its own, like an API service, guards each method instead of its constructor.
        const methods = value.body.body.filter(
            (member) =>
                member.type === 'MethodDefinition' && member.kind !== 'constructor' && !keyName(member.key).startsWith('_'),
        );
        const guardsEveryMethod = methods.length > 0 && methods.every((member) => leadingGuardFlag(member.value) !== null);

        return guardsEveryMethod ? methods.map((member) => member.value) : [value];
    }

    const callee = value?.type === 'CallExpression' ? value.callee : null;
    const calleeName = callee?.type === 'MemberExpression' ? keyName(callee.property) : keyName(callee);

    if (FUNCTION_WRAPPERS.includes(calleeName)) {
        const wrapped = value.arguments.filter(isFunction);

        return wrapped.length > 0 ? wrapped : null;
    }

    if (value?.type === 'ObjectExpression' && node.type === 'Property' && sectionOf(node) === 'computed') {
        const accessors = value.properties.filter(
            (property) =>
                property.type === 'Property' && ['get', 'set'].includes(keyName(property.key)) && isFunction(property.value),
        );

        return accessors.length > 0 ? accessors.map((accessor) => accessor.value) : null;
    }

    return null;
}

/**
 * The flag of `fn`'s first statement when it is a `…triggerDeprecationOrThrow('<flag>', …)` call: `''` for a
 * flag that is not a string literal, `null` when the first statement is something else.
 *
 * @example
 * leadingGuardFlag(oldMethodFunction); // 'V6_8_0_0'
 */
function leadingGuardFlag(fn) {
    if (fn.body?.type !== 'BlockStatement') {
        return null;
    }

    const first = fn.body.body[0];
    const call = first?.type === 'ExpressionStatement' ? first.expression : null;
    const callee = call?.type === 'CallExpression' ? call.callee : null;

    if (callee?.type !== 'MemberExpression' || keyName(callee.property) !== GUARD_NAME) {
        return null;
    }

    const flag = call.arguments[0];

    return flag?.type === 'Literal' && typeof flag.value === 'string' ? flag.value : '';
}

/**
 * This rule enforces the runtime side of adr/2026-08-10-administration-javascript-deprecation-guards.md: every
 * `@deprecated tag:vX.Y.Z` declaration that runs when it is used has to say so at runtime.
 *
 * - A function, method, computed accessor or class constructor calls
 *   `Shopware.Feature.triggerDeprecationOrThrow('<major flag>', '…')` as its first statement, so no early return
 *   or condition can skip it.
 * - A component and a prop carry the `deprecated: { version, comment }` option the deprecation plugin reads. A
 *   component counts as deprecated by its own annotation, its file header, or the `Component.register()` call.
 *
 * What the marker sits on decides it: a value, a type, an import or a statement inside a body has no use to guard.
 * Neither do `@private` and `_`-prefixed declarations and "Will be private" deprecations, which are no public
 * contract, and the functions of `data`, `watch`, `provide`, `inject`, store `state` and `getters`, which core runs
 * for every instance. A file header covers the component the file exports, or every export of any other file.
 * A function that must not warn on use takes an `eslint-disable-next-line … -- <reason>`.
 */
/** @type {import('eslint').Rule.RuleModule} */
module.exports = {
    meta: {
        type: 'problem',

        docs: {
            description:
                'Deprecated Administration functions, components and props must trigger their deprecation at runtime',
            recommended: true,
            url: 'https://github.com/shopware/shopware/blob/trunk/adr/2026-08-10-administration-javascript-deprecation-guards.md',
        },

        schema: [],

        messages: {
            missingGuard:
                "The deprecated \"{{name}}\" has to call Shopware.Feature.triggerDeprecationOrThrow('{{flag}}', '…') " +
                'as its first statement.',
            missingClassGuard:
                "The deprecated class \"{{name}}\" has to call Shopware.Feature.triggerDeprecationOrThrow('{{flag}}', '…') " +
                'as the first statement of its constructor, or of every method.',
            flagMismatch:
                'The guard of the deprecated "{{name}}" has to use the major flag "{{flag}}" that "@deprecated tag:{{version}}" announces.',
            missingComponentOption:
                "The deprecated component needs a root \"deprecated: { version: '{{version}}', comment: '…' }\" option, " +
                'so the deprecation plugin triggers it on creation.',
            missingRegisteredComponentOption:
                "The deprecated component registered here needs a root \"deprecated: { version: '{{version}}', comment: '…' }\" " +
                'option in {{file}}, so the deprecation plugin triggers it on creation.',
            versionMismatch:
                'The "deprecated" option of "{{name}}" has to use the version "{{version}}" that "@deprecated tag:" announces.',
            missingPropOption:
                'The deprecated prop "{{name}}" needs a "deprecated: { version: \'{{version}}\', comment: \'…\' }" option, ' +
                'so the deprecation plugin triggers it when a parent passes it.',
        },
    },

    create(context) {
        const sourceCode = context.sourceCode;
        const comments = sourceCode.getAllComments();

        /**
         * The declaration a comment documents: the node that starts at the next code token, widened to the whole
         * declaration.
         *
         * @example
         * documentedDeclaration(jsdoc); // the `Property` of `oldMethod() {}`
         */
        function documentedDeclaration(comment) {
            // Skips comments in between, like an `eslint-disable-next-line` for another rule.
            const token = sourceCode.getTokenAfter(comment);

            if (!token) {
                return null;
            }

            let node = sourceCode.getNodeByRangeIndex(token.range[0]);

            while (
                node?.parent &&
                node.parent.type !== 'Program' &&
                node.parent.range[0] === node.range[0] &&
                !['Property', 'MethodDefinition', 'PropertyDefinition'].includes(node.type)
            ) {
                node = node.parent;
            }

            if ((node?.type === 'ExportNamedDeclaration' || node?.type === 'ExportDefaultDeclaration') && node.declaration) {
                return node.type === 'ExportDefaultDeclaration' && componentConfigOf(node) ? node : node.declaration;
            }

            return node;
        }

        /**
         * The `//` lines a line comment belongs to, so `@deprecated` and `@private` on adjacent lines count as one
         * annotation. Block comments stand alone.
         *
         * @example
         * annotationOf(lineComment); // [line1, line2]
         */
        function annotationOf(comment) {
            if (comment.type !== 'Line') {
                return [comment];
            }

            const index = comments.indexOf(comment);
            let end = index;

            while (
                comments[end + 1]?.type === 'Line' &&
                comments[end + 1].loc.start.line === comments[end].loc.end.line + 1
            ) {
                end += 1;
            }

            let start = index;

            while (
                comments[start - 1]?.type === 'Line' &&
                comments[start - 1].loc.end.line === comments[start].loc.start.line - 1
            ) {
                start -= 1;
            }

            return comments.slice(start, end + 1);
        }

        function checkFunctions(node, functions, name, tag) {
            const flag = toMajorFlag(tag);

            functions.forEach((fn) => {
                if (fn.type === 'ClassDeclaration' || fn.type === 'ClassExpression') {
                    context.report({ node, messageId: 'missingClassGuard', data: { name, flag } });

                    return;
                }

                const guardFlag = leadingGuardFlag(fn);

                if (guardFlag === null) {
                    context.report({ node, messageId: 'missingGuard', data: { name, flag } });

                    return;
                }

                if (guardFlag.toUpperCase().replace(/\./g, '_') !== flag) {
                    context.report({
                        node,
                        messageId: 'flagMismatch',
                        data: { name, flag, version: `v${tag.slice(1, 5).filter(Boolean).join('.')}` },
                    });
                }
            });
        }

        function checkDeclaration(node, tag) {
            if (!node || node.type.startsWith('TS')) {
                return;
            }

            const name = declarationName(node);

            if (name.startsWith('_')) {
                return;
            }

            const componentFile = registeredFile(node, context.filename);

            if (componentFile) {
                if (!hasRootDeprecatedOption(componentFile)) {
                    context.report({
                        node,
                        messageId: 'missingRegisteredComponentOption',
                        data: {
                            version: toVersion(tag),
                            file: path.relative(path.dirname(context.filename), componentFile),
                        },
                    });
                }

                return;
            }

            const componentConfig = componentConfigOf(node);

            if (componentConfig) {
                checkOption(node, deprecatedOption(componentConfig), 'missingComponentOption', 'component', tag);

                return;
            }

            const section = node.type === 'Property' ? sectionOf(node) : '';

            if (UNGUARDED_SECTIONS.includes(section)) {
                return;
            }

            if (section === 'props') {
                checkOption(node, deprecatedOption(node.value), 'missingPropOption', name, tag);

                return;
            }

            const functions = guardedFunctions(node);

            if (functions) {
                checkFunctions(node, functions, name, tag);
            }
        }

        function checkOption(node, option, missingMessageId, name, tag) {
            const version = toVersion(tag);

            if (!option) {
                context.report({ node, messageId: missingMessageId, data: { name, version } });

                return;
            }

            if (!versionMatches(option, tag)) {
                context.report({ node: option, messageId: 'versionMismatch', data: { name, version } });
            }
        }

        /**
         * The declarations a deprecation comment covers. A file header covers the component the file exports, or
         * every export of a file that exports no component. Any other comment covers the declaration after it.
         *
         * @example
         * coveredDeclarations(fileHeader); // [FunctionDeclaration a, FunctionDeclaration b]
         */
        function coveredDeclarations(annotation) {
            const isFileHeader = annotation.some((entry) => entry.value.includes('@sw-package'));

            if (!isFileHeader) {
                return [documentedDeclaration(annotation[annotation.length - 1])];
            }

            const defaultExport = sourceCode.ast.body.find((statement) => statement.type === 'ExportDefaultDeclaration');

            if (defaultExport && componentConfigOf(defaultExport)) {
                return [defaultExport];
            }

            return exportedDeclarations(sourceCode.ast).filter(
                (declaration) =>
                    !sourceCode
                        .getCommentsBefore(declaration.parent?.type?.startsWith('Export') ? declaration.parent : declaration)
                        .some((entry) => entry.value.includes('@private')),
            );
        }

        function check(comment) {
            const tag = TAG_PATTERN.exec(comment.value);

            if (!tag) {
                return;
            }

            const annotation = annotationOf(comment);

            if (annotation.some((entry) => entry.value.includes('@private') || VISIBILITY_PATTERN.test(entry.value))) {
                return;
            }

            coveredDeclarations(annotation).forEach((node) => checkDeclaration(node, tag));
        }

        return {
            Program() {
                comments.forEach(check);
            },
        };
    },
};
