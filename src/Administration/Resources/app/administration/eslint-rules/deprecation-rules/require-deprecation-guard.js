/**
 * @sw-package framework
 */

const fs = require('fs');
const path = require('path');

const TAG_PATTERN = /@deprecated\s+tag:v(\d+\.\d+\.\d+(?:\.\d+)?)/;
const WILL_BE_PRIVATE_PATTERN = /@deprecated\s+tag:\S+\s*-\s*will be @?private/i;
const GUARD_NAME = 'triggerDeprecationOrThrow';
const GUARD_SNIPPET = `Shopware.Feature.${GUARD_NAME}('{{flag}}', '…')`;
const OPTION_SNIPPET = "deprecated: { version: '{{version}}', comment: '…' }";

const DECLARATIONS = [
    'Property',
    'MethodDefinition',
    'PropertyDefinition',
    'FunctionDeclaration',
    'ClassDeclaration',
    'VariableDeclaration',
    'ExpressionStatement',
].join(', ');

// They run the wrapped function on every use, so the guard goes into the wrapped function.
const FUNCTION_WRAPPERS = new Set(['debounce', 'throttle']);

// Core runs these for every instance, so a guard there would fire on every use instead of on the legacy one.
const UNGUARDED_SECTIONS = new Set([
    'data',
    'watch',
    'provide',
    'inject',
    'state',
    'getters',
]);

// Only a component config has these, so `export default { … }` of a plain object is no component.
const COMPONENT_OPTIONS = new Set([
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
]);

const COMPONENT_FILE_SUFFIXES = [
    '',
    '.js',
    '.ts',
    '/index.js',
    '/index.ts',
];

function keyName(node) {
    if (node?.type === 'Identifier') {
        return node.name;
    }

    return node?.type === 'Literal' ? String(node.value) : '';
}

function stringValue(node) {
    return node?.type === 'Literal' && typeof node.value === 'string' ? node.value : null;
}

function findProperty(object, name) {
    if (object?.type !== 'ObjectExpression') {
        return undefined;
    }

    return object.properties.find((property) => property.type === 'Property' && keyName(property.key) === name);
}

function isFunction(node) {
    return ['FunctionExpression', 'ArrowFunctionExpression', 'FunctionDeclaration'].includes(node?.type);
}

function isClass(node) {
    return node?.type === 'ClassDeclaration' || node?.type === 'ClassExpression';
}

function isGuardCall(node) {
    return (
        node?.type === 'CallExpression' &&
        node.callee.type === 'MemberExpression' &&
        keyName(node.callee.property) === GUARD_NAME
    );
}

function leadingGuard(fn) {
    const first = fn.body?.type === 'BlockStatement' ? fn.body.body[0] : null;

    return first?.type === 'ExpressionStatement' && isGuardCall(first.expression) ? first.expression : null;
}

function unwrapTypeAssertions(node) {
    let current = node;

    while (['TSAsExpression', 'TSSatisfiesExpression', 'TSNonNullExpression'].includes(current?.type)) {
        current = current.expression;
    }

    return current;
}

/**
 * `v6.8` and `6.8.0.0` both become `v6.8.0.0`, the version the `deprecated` option takes.
 */
function normalizeVersion(value) {
    const [
        major,
        minor = '0',
        patch = '0',
        build = '0',
    ] = value.replace(/^v/i, '').split('.');

    return `v${major}.${minor}.${patch}.${build}`;
}

/**
 * `v6.8.0.0` becomes `V6_8_0_0`, the major flag `triggerDeprecationOrThrow` takes.
 */
function toFlag(value) {
    return value.toUpperCase().replace(/\./g, '_');
}

/**
 * The deprecation the comments announce, or `null` when they announce none or only take something out of the
 * public API, like "Will be private", which keeps it working.
 */
function parseTag(comments) {
    const text = comments.map((comment) => comment.value).join('\n');
    const match = TAG_PATTERN.exec(text);

    if (!match || text.includes('@private') || WILL_BE_PRIVATE_PATTERN.test(text)) {
        return null;
    }

    const version = normalizeVersion(match[1]);

    return { announced: `v${match[1]}`, version, flag: toFlag(version) };
}

// The value is what has to carry the guard.
function nameAndValue(node) {
    switch (node.type) {
        case 'Property':
        case 'MethodDefinition':
        case 'PropertyDefinition':
            return { name: keyName(node.key), value: node.value };
        case 'FunctionDeclaration':
        case 'ClassDeclaration':
            return { name: keyName(node.id), value: node };
        case 'VariableDeclaration':
            return { name: keyName(node.declarations[0]?.id), value: node.declarations[0]?.init };
        case 'ExpressionStatement': {
            const { expression } = node;

            if (expression.type !== 'AssignmentExpression') {
                return { name: '', value: null };
            }

            const name = expression.left.type === 'MemberExpression' ? keyName(expression.left.property) : '';

            return { name, value: expression.right };
        }
        default:
            return { name: '', value: node };
    }
}

/**
 * The Vue or store option a property belongs to: `methods` for `methods: { oldMethod() {} }`, and `data` for
 * `data() { return { … } }` or `state: () => ({ … })`.
 */
function sectionOf(property) {
    const object = property.parent;
    const owner = object?.type === 'ObjectExpression' ? object.parent : null;

    if (owner?.type === 'Property' && owner.value === object) {
        return keyName(owner.key);
    }

    const isReturned =
        owner?.type === 'ReturnStatement' || (owner?.type === 'ArrowFunctionExpression' && owner.body === object);

    if (!isReturned) {
        return '';
    }

    for (let current = owner; current; current = current.parent) {
        if (current.type === 'Property') {
            return keyName(current.key);
        }
    }

    return '';
}

/**
 * The functions whose first statement has to be the guard: the function itself, both accessors of a computed
 * written as `{ get, set }`, or the function a wrapper like `Utils.debounce(function () { … })` wraps.
 */
function guardedFunctions(value, section) {
    if (isFunction(value)) {
        return [value];
    }

    if (value?.type === 'CallExpression') {
        const { callee } = value;
        const calleeName = callee.type === 'MemberExpression' ? keyName(callee.property) : keyName(callee);

        return FUNCTION_WRAPPERS.has(calleeName) ? value.arguments.filter(isFunction) : [];
    }

    if (value?.type === 'ObjectExpression' && section === 'computed') {
        return ['get', 'set'].map((accessor) => findProperty(value, accessor)?.value).filter(isFunction);
    }

    return [];
}

/**
 * The guarded constructor, or else every public method when all of them are guarded, which is how a class core
 * creates on its own, like an API service, guards itself. Empty when the class is not guarded.
 */
function guardedMembers(classNode) {
    const methods = classNode.body.body.filter((member) => member.type === 'MethodDefinition');
    const constructor = methods.find((member) => member.kind === 'constructor');

    if (constructor && leadingGuard(constructor.value)) {
        return [constructor];
    }

    const publicMethods = methods.filter((member) => member.kind !== 'constructor' && !keyName(member.key).startsWith('_'));
    const guardsEveryMethod = publicMethods.length > 0 && publicMethods.every((member) => leadingGuard(member.value));

    return guardsEveryMethod ? publicMethods : [];
}

/**
 * The config of `export default { … }`, or of `export default Component.wrapComponentConfig({ … })` and any other
 * call that gets it as first argument. `null` when the default export is no component.
 */
function componentConfigOf(node) {
    if (node.type !== 'ExportDefaultDeclaration') {
        return null;
    }

    const { declaration } = node;
    const config = declaration.type === 'CallExpression' ? declaration.arguments[0] : declaration;
    const isComponent =
        config?.type === 'ObjectExpression' &&
        config.properties.some((property) => property.type === 'Property' && COMPONENT_OPTIONS.has(keyName(property.key)));

    return isComponent ? config : null;
}

/**
 * The relative import of a `Component.register('sw-x', () => import('./sw-x'))` or
 * `Component.extend('sw-x', 'sw-base', () => import('./sw-x'))` call, or `null` for any other statement.
 */
function registeredImportSource(node) {
    const call = node.type === 'ExpressionStatement' ? node.expression : null;
    const isRegistration =
        call?.type === 'CallExpression' &&
        call.callee.type === 'MemberExpression' &&
        ['register', 'extend'].includes(keyName(call.callee.property));
    const loader = isRegistration ? call.arguments.at(-1) : null;
    const source = isFunction(loader) && loader.body.type === 'ImportExpression' ? stringValue(loader.body.source) : null;

    return source?.startsWith('.') ? source : null;
}

function resolveComponentFile(filename, source) {
    const base = path.resolve(path.dirname(filename), source);

    return (
        COMPONENT_FILE_SUFFIXES.map((suffix) => `${base}${suffix}`).find((file) =>
            fs.statSync(file, { throwIfNoEntry: false })?.isFile(),
        ) ?? null
    );
}

// A root option of a component config is indented by four spaces.
function hasRootDeprecatedOption(file) {
    return /^ {4}deprecated: \{/m.test(fs.readFileSync(file, 'utf8'));
}

/**
 * This rule enforces the runtime side of adr/2026-08-10-administration-javascript-deprecation-guards.md.
 * What a `@deprecated tag:` marker sits on decides what it requires:
 *
 * - A function, method, computed accessor or class calls `Shopware.Feature.triggerDeprecationOrThrow('<major flag>', '…')`
 *   as its first statement, so no early return or condition can skip it.
 * - A component and a prop carry the `deprecated: { version, comment }` option the deprecation plugin reads.
 * - A file header covers the component the file exports, or every export of any other file.
 *
 * The other way round, every `triggerDeprecationOrThrow('<flag>', …)` call needs a marker on a declaration that encloses
 * it, or in the file header.
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
            missingGuard: `The deprecated "{{name}}" has to call ${GUARD_SNIPPET} as its first statement.`,
            missingClassGuard:
                `The deprecated class "{{name}}" has to call ${GUARD_SNIPPET} ` +
                'as the first statement of its constructor, or of every method.',
            flagMismatch:
                'The guard of the deprecated "{{name}}" has to use the major flag "{{flag}}" ' +
                'that "@deprecated tag:{{announced}}" announces.',
            missingComponentOption:
                `The deprecated component needs a root "${OPTION_SNIPPET}" option, ` +
                'so the deprecation plugin triggers it on creation.',
            missingRegisteredComponentOption:
                `The deprecated component registered here needs a root "${OPTION_SNIPPET}" option in {{file}}, ` +
                'so the deprecation plugin triggers it on creation.',
            versionMismatch:
                'The "deprecated" option of "{{name}}" has to use the version "{{version}}" that "@deprecated tag:" announces.',
            missingDeprecationMarker:
                `This ${GUARD_NAME}() call needs a "@deprecated tag:" marker on the declaration it guards, ` +
                'or on a comment right above it.',
            missingPropOption:
                `The deprecated prop "{{name}}" needs a "${OPTION_SNIPPET}" option, ` +
                'so the deprecation plugin triggers it when a parent passes it.',
        },
    },

    create(context) {
        const { sourceCode } = context;
        const headers = sourceCode.getAllComments().filter((comment) => comment.value.includes('@sw-package'));

        function leadingComments(node) {
            const outer = node.parent?.type.startsWith('Export') ? node.parent : node;

            return sourceCode.getCommentsBefore(outer).filter((comment) => !headers.includes(comment));
        }

        function declarationOf(identifier) {
            const definition = sourceCode.getScope(identifier).set.get(identifier.name)?.defs[0];

            if (definition?.type === 'Variable') {
                return definition.parent;
            }

            return ['FunctionName', 'ClassName'].includes(definition?.type) ? definition.node : null;
        }

        function exportedDeclarations(program) {
            return program.body
                .flatMap((statement) => {
                    if (statement.type === 'ExportDefaultDeclaration') {
                        const { declaration } = statement;

                        return [declaration.type === 'Identifier' ? declarationOf(declaration) : declaration];
                    }

                    if (statement.type !== 'ExportNamedDeclaration' || statement.source) {
                        return [];
                    }

                    return statement.declaration
                        ? [statement.declaration]
                        : statement.specifiers.map((specifier) => declarationOf(specifier.local));
                })
                .filter(Boolean);
        }

        function headerDeclarations(program) {
            const component = program.body.find(componentConfigOf);

            if (component) {
                return [component];
            }

            return exportedDeclarations(program).filter(
                (declaration) => !leadingComments(declaration).some((comment) => comment.value.includes('@private')),
            );
        }

        function hasDeprecationMarker(node) {
            const isTagged = (comment) => TAG_PATTERN.test(comment.value);
            const enclosing = [...sourceCode.getAncestors(node), node].filter((ancestor) => ancestor.type !== 'Program');

            return (
                enclosing.some((ancestor) => sourceCode.getCommentsBefore(ancestor).some(isTagged)) || headers.some(isTagged)
            );
        }

        function report(declaration, messageId, data = {}, node = declaration.node) {
            const { name, tag } = declaration;

            context.report({
                node,
                messageId,
                data: { name, flag: tag.flag, version: tag.version, announced: tag.announced, ...data },
            });
        }

        function checkGuard(declaration, fn) {
            const guard = leadingGuard(fn);

            if (!guard) {
                return report(declaration, 'missingGuard');
            }

            const flag = stringValue(guard.arguments[0]);

            if (flag === null || toFlag(flag) !== declaration.tag.flag) {
                report(declaration, 'flagMismatch');
            }
        }

        function checkClass(declaration, classNode) {
            const members = guardedMembers(classNode);

            if (members.length === 0) {
                return report(declaration, 'missingClassGuard');
            }

            members.forEach((member) => checkGuard(declaration, member.value));
        }

        function checkOption(declaration, option, missingMessageId) {
            if (!option) {
                return report(declaration, missingMessageId);
            }

            const version = stringValue(findProperty(option, 'version')?.value);

            if (version !== null && normalizeVersion(version) !== declaration.tag.version) {
                report(declaration, 'versionMismatch', {}, option);
            }
        }

        function checkRegisteredComponent(declaration, source) {
            const file = resolveComponentFile(context.filename, source);

            if (file && !hasRootDeprecatedOption(file)) {
                report(declaration, 'missingRegisteredComponentOption', {
                    file: path.relative(path.dirname(context.filename), file),
                });
            }
        }

        function checkDeclaration(node, tag) {
            if (node.type.startsWith('TS')) {
                return;
            }

            const componentConfig = componentConfigOf(node);
            const { name, value } = componentConfig ? { name: 'component', value: null } : nameAndValue(node);

            if (name.startsWith('_')) {
                return;
            }

            const declaration = { node, name, tag };
            const importSource = registeredImportSource(node);

            if (importSource) {
                return checkRegisteredComponent(declaration, importSource);
            }

            if (componentConfig) {
                return checkOption(
                    declaration,
                    findProperty(componentConfig, 'deprecated')?.value,
                    'missingComponentOption',
                );
            }

            const section = node.type === 'Property' ? sectionOf(node) : '';

            if (UNGUARDED_SECTIONS.has(section)) {
                return;
            }

            if (section === 'props') {
                return checkOption(declaration, findProperty(node.value, 'deprecated')?.value, 'missingPropOption');
            }

            const target = unwrapTypeAssertions(value);

            if (isClass(target)) {
                return checkClass(declaration, target);
            }

            guardedFunctions(target, section).forEach((fn) => checkGuard(declaration, fn));
        }

        function checkAnnotated(commentTarget, declaration) {
            const tag = parseTag(leadingComments(commentTarget));

            if (tag) {
                checkDeclaration(declaration, tag);
            }
        }

        return {
            Program(program) {
                const tag = parseTag(headers);

                if (tag) {
                    headerDeclarations(program).forEach((declaration) => checkDeclaration(declaration, tag));
                }
            },

            [DECLARATIONS](node) {
                checkAnnotated(node, node);
            },

            // A function or class declaration as default export is visited as a declaration of its own.
            ExportDefaultDeclaration(node) {
                const declaration = componentConfigOf(node) ? node : node.declaration;

                if (declaration.type !== 'FunctionDeclaration' && declaration.type !== 'ClassDeclaration') {
                    checkAnnotated(node, declaration);
                }
            },

            CallExpression(call) {
                if (isGuardCall(call) && stringValue(call.arguments[0]) !== null && !hasDeprecationMarker(call)) {
                    context.report({ node: call, messageId: 'missingDeprecationMarker' });
                }
            },
        };
    },
};
