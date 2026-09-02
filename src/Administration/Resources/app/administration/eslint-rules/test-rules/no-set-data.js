/**
 * @sw-package framework
 */

const fs = require('fs');
const path = require('path');

const VUE_SUFFIX = '.vue';

/**
 * Derives the component a spec belongs to from its own path.
 *
 * `sw-thing/sw-thing.spec.js` and `sw-thing/sw-thing.spec/rendering.spec.js` both belong to `sw-thing`,
 * declared in the `sw-thing` directory.
 */
function deriveSpecSubject(filename) {
    const directory = path.dirname(filename);
    const specDirectory = path.basename(directory);

    if (specDirectory.endsWith('.spec')) {
        return {
            componentName: specDirectory.slice(0, -'.spec'.length),
            directory: path.dirname(directory),
        };
    }

    return {
        componentName: path.basename(filename).replace(/\.spec\.(js|ts)$/, ''),
        directory,
    };
}

/**
 * Whether the component a spec is named after has been converted to a native setup SFC.
 *
 * Administration specs live in the directory of the code they test, so a converted component turns up as
 * `<dir>/index.vue` or `<dir>/<name>.vue` right beside the spec - both of which the transform registers
 * under the name the spec is derived from. `index.vue` counts only when the directory carries that name,
 * so `app/mixin/listing.mixin.spec.js` is not gated by an unrelated `app/mixin/index.vue`.
 */
function hasConvertedSibling(filename) {
    const subject = deriveSpecSubject(filename);

    if (fs.existsSync(path.join(subject.directory, `${subject.componentName}${VUE_SUFFIX}`))) {
        return true;
    }

    return (
        path.basename(subject.directory) === subject.componentName &&
        fs.existsSync(path.join(subject.directory, `index${VUE_SUFFIX}`))
    );
}

const MOUNT_FUNCTIONS = new Set([
    'mount',
    'shallowMount',
]);

/**
 * What a wrapper variable was mounted from, as far as it can be traced.
 *
 * `optionsApi` is the only verdict the rule acts on, and only to stay quiet. Tracing never turns a
 * silent call into a reported one, so a resolver that gives up costs nothing: the file-level signal still
 * decides. That keeps a wrong trace from hiding the very no-op the rule exists to catch.
 */
const UNTRACED = { kind: 'untraced' };

function lookUpVariable(scope, name) {
    for (let current = scope; current !== null; current = current.upper) {
        const variable = current.variables.find((candidate) => candidate.name === name);

        if (variable !== undefined) {
            return variable;
        }
    }

    return null;
}

/**
 * The expression a component literal was mounted with, if it is plainly Options API.
 *
 * A `setup()` block puts state in `setupState` exactly like a native setup SFC does, so an inline
 * component that has one is no safer than a converted file and must stay reported.
 */
function describeComponentLiteral(node) {
    const names = node.properties
        .filter((property) => property.type === 'Property' && !property.computed)
        .map((property) => (property.key.type === 'Identifier' ? property.key.name : property.key.value));

    if (names.includes('setup')) {
        return UNTRACED;
    }

    return names.includes('data') ? { kind: 'optionsApi' } : UNTRACED;
}

/**
 * The expression a function hands back, ignoring returns from functions nested inside it.
 */
function findReturnedExpression(functionNode) {
    const body = functionNode.body;

    if (body.type !== 'BlockStatement') {
        return body;
    }

    let returned = null;

    (function walk(node) {
        if (returned !== null || node === null || typeof node?.type !== 'string') {
            return;
        }

        if (node.type === 'ReturnStatement') {
            returned = node.argument;

            return;
        }

        if (node !== body && /Function(Declaration|Expression)$/.test(node.type)) {
            return;
        }

        for (const key of Object.keys(node)) {
            if (key === 'parent') {
                continue;
            }

            const value = node[key];

            if (Array.isArray(value)) {
                value.forEach(walk);
            } else if (value !== null && typeof value?.type === 'string') {
                walk(value);
            }
        }
    })(body);

    return returned;
}

function findFunctionNode(variable) {
    for (const definition of variable?.defs ?? []) {
        if (definition.node.type === 'FunctionDeclaration') {
            return definition.node;
        }

        const init = definition.node.init;

        if (init && /^(ArrowFunctionExpression|FunctionExpression)$/.test(init.type)) {
            return init;
        }
    }

    return null;
}

/**
 * The expression a variable last holds, plus the destructuring key it was bound through.
 *
 * `const { wrapper } = await createWrapper()` binds through the key `wrapper`, so the helper's return
 * value has to be narrowed to that property before the trace can continue.
 */
function findAssignedExpression(variable) {
    const definition = variable?.defs?.[variable.defs.length - 1];
    const write = variable?.references.filter((reference) => reference.writeExpr).pop();
    const expression = definition?.node?.type === 'VariableDeclarator' ? definition.node.init : null;
    const identifier = definition?.node?.id;

    let key = null;

    if (identifier?.type === 'ObjectPattern') {
        const property = identifier.properties.find(
            (candidate) => candidate.type === 'Property' && candidate.value.name === variable.name,
        );

        key = property?.key.type === 'Identifier' ? property.key.name : null;
    }

    return { expression: expression ?? write?.writeExpr ?? null, key };
}

/**
 * Traces a wrapper expression back to the component `mount()` received.
 *
 * `key` narrows an object the trace passed through, for a wrapper destructured out of a helper's return.
 */
function traceWrapper(node, scope, sourceCode, key = null, depth = 0) {
    if (node === null || node === undefined || depth > 6) {
        return UNTRACED;
    }

    switch (node.type) {
        case 'AwaitExpression':
            return traceWrapper(node.argument, scope, sourceCode, key, depth + 1);

        case 'TSAsExpression':
        case 'TSNonNullExpression':
            return traceWrapper(node.expression, scope, sourceCode, key, depth + 1);

        case 'ObjectExpression': {
            const property = node.properties.find(
                (candidate) => candidate.type === 'Property' && !candidate.computed && candidate.key.name === key,
            );

            return property === undefined ? UNTRACED : traceWrapper(property.value, scope, sourceCode, null, depth + 1);
        }

        case 'Identifier': {
            const variable = lookUpVariable(scope, node.name);

            if (variable === null) {
                return UNTRACED;
            }

            const assigned = findAssignedExpression(variable);

            return traceWrapper(assigned.expression, variable.scope, sourceCode, assigned.key ?? key, depth + 1);
        }

        case 'CallExpression': {
            if (node.callee.type !== 'Identifier') {
                return UNTRACED;
            }

            if (MOUNT_FUNCTIONS.has(node.callee.name)) {
                return describeMountArgument(node.arguments[0], scope, sourceCode, depth + 1);
            }

            const helper = findFunctionNode(lookUpVariable(scope, node.callee.name));

            if (helper === null) {
                return UNTRACED;
            }

            const helperScope = sourceCode.scopeManager.acquire(helper) ?? scope;

            return traceWrapper(findReturnedExpression(helper), helperScope, sourceCode, key, depth + 1);
        }

        default:
            return UNTRACED;
    }
}

function describeMountArgument(node, scope, sourceCode, depth) {
    if (node === null || node === undefined || depth > 6) {
        return UNTRACED;
    }

    switch (node.type) {
        case 'AwaitExpression':
            return describeMountArgument(node.argument, scope, sourceCode, depth + 1);

        case 'TSAsExpression':
        case 'TSNonNullExpression':
            return describeMountArgument(node.expression, scope, sourceCode, depth + 1);

        case 'ObjectExpression':
            return describeComponentLiteral(node);

        case 'Identifier': {
            const variable = lookUpVariable(scope, node.name);
            const assigned = findAssignedExpression(variable);

            return assigned.expression === null
                ? UNTRACED
                : describeMountArgument(assigned.expression, variable.scope, sourceCode, depth + 1);
        }

        default:
            return UNTRACED;
    }
}

function isIdentifierLike(name) {
    return /^[A-Za-z_$][A-Za-z0-9_$]*$/.test(name);
}

function buildAccessor(property) {
    const key = property.key.type === 'Identifier' ? property.key.name : property.key.value;

    return isIdentifierLike(key) ? `.${key}` : `[${JSON.stringify(key)}]`;
}

/**
 * Decides whether a `setData` call can be rewritten mechanically, and why not when it cannot.
 *
 * The rewrite is not a semantics-preserving refactor of a working call - on a native setup component the
 * call writes nothing at all. It reproduces what the spec author meant: assign this state to this value.
 * That reading only holds where the object literal spells the keys out flatly, so everything else is
 * reported without a fix.
 */
function classifySetDataCall(node, sourceCode) {
    if (node.callee.object.type !== 'Identifier') {
        return { blocker: 'the wrapper is not a plain variable' };
    }

    const awaitExpression = node.parent;
    const statement = awaitExpression?.parent;

    if (awaitExpression?.type !== 'AwaitExpression' || statement?.type !== 'ExpressionStatement') {
        return { blocker: 'the call is not a standalone awaited statement' };
    }

    const indentation = sourceCode.lines[statement.loc.start.line - 1].slice(0, statement.loc.start.column);

    if (indentation.trim() !== '') {
        return { blocker: 'the statement does not start its own line' };
    }

    const argument = node.arguments[0];

    if (node.arguments.length !== 1 || argument.type !== 'ObjectExpression') {
        return { blocker: 'the argument is not an object literal' };
    }

    for (const property of argument.properties) {
        if (property.type !== 'Property' || property.kind !== 'init' || property.method) {
            return { blocker: 'the object literal does not spell out plain key/value pairs' };
        }

        if (property.computed || (property.key.type !== 'Identifier' && typeof property.key.value !== 'string')) {
            return { blocker: 'a computed key hides which state is written' };
        }

        if (property.value.type === 'ObjectExpression') {
            return { blocker: 'a nested object literal merges into the existing value instead of replacing it' };
        }
    }

    return { statement, indentation, properties: argument.properties };
}

/**
 * Reports `wrapper.setData()` in specs that mount a native setup component, where the call does nothing.
 *
 * `setData` is `mergeDeep(vm.$data, data)`. A native setup component keeps its state in `setupState`, and
 * nothing bridges the two, so the write lands in an object the component never reads. It does not throw
 * either: the global Meteor SDK mixin gives every component a real `data()`, so `$data` is never the
 * frozen `EMPTY_OBJ` whose mutation would have raised. The spec then fails on a later assertion, or keeps
 * passing while asserting nothing.
 *
 * A spec counts as mounting a native setup component when the component it is named after is a `.vue`
 * file in its own directory, or when it imports a `.vue` file directly. Within such a spec an individual
 * call is spared when its wrapper traces back to an Options API component literal: mounting an inline host
 * that registers the converted component as one of its children is the common shape, and `setData` on the
 * host is correct and stays correct.
 *
 * A spec that mounts some *other* component - not the one it is named after - is therefore not covered
 * until its own component is converted too. Resolving those names would mean indexing every `.vue` file
 * under `src`, and across the Administration suite it buys nothing: every `setData` call sits either on
 * the spec's own component or on an inline host.
 *
 * @type {import('eslint').Rule.RuleModule}
 */
module.exports = {
    meta: {
        type: 'problem',
        docs: {
            description: 'Disallow wrapper.setData() in specs for native setup components, where it is a silent no-op',
            category: 'Possible Errors',
            recommended: true,
        },
        fixable: 'code',
        schema: [],
        messages: {
            silentNoOp:
                'setData() merges into $data, which a native setup component never reads, so this write is ' +
                'silently discarded while the spec keeps asserting. Assign the state directly instead: ' +
                '`{{ receiver }}.vm.<key> = <value>`, followed by `await nextTick()`.',
            silentNoOpManualRewrite:
                'setData() merges into $data, which a native setup component never reads, so this write is ' +
                'silently discarded while the spec keeps asserting. Rewrite it as direct assignments to ' +
                '`.vm`, followed by `await nextTick()`. It cannot be rewritten automatically because ' +
                '{{ blocker }}.',
        },
    },

    create(context) {
        const filename = context.filename ?? context.getFilename();
        const sourceCode = context.sourceCode ?? context.getSourceCode();

        const setDataCalls = [];
        let importsVueFile = false;
        let vueImport = null;
        let nextTickLocalName = null;
        let firstImport = null;
        let importFixEmitted = false;

        function mountsNativeSetupComponent() {
            return importsVueFile || hasConvertedSibling(filename);
        }

        /**
         * Whether this one call is on a wrapper that demonstrably holds an Options API component, in a spec
         * that mounts a native setup one elsewhere.
         */
        function targetsAnUnconvertedComponent(node) {
            const receiver = node.callee.object;

            if (receiver.type !== 'Identifier') {
                return false;
            }

            return traceWrapper(receiver, sourceCode.getScope(receiver), sourceCode).kind === 'optionsApi';
        }

        /**
         * Adds `nextTick` to the file's imports, once per fix pass.
         *
         * Every fixed call needs the binding but only one may insert it, otherwise the inserts collide at
         * the same offset and ESLint drops all but one - leaving the rest for another pass.
         */
        function buildNextTickImportFix(fixer) {
            if (nextTickLocalName !== null || importFixEmitted) {
                return null;
            }

            importFixEmitted = true;

            const namedSpecifiers = vueImport?.specifiers.filter((s) => s.type === 'ImportSpecifier') ?? [];

            if (namedSpecifiers.length > 0) {
                return fixer.insertTextAfter(namedSpecifiers[namedSpecifiers.length - 1], ', nextTick');
            }

            const defaultSpecifier = vueImport?.specifiers.find((s) => s.type === 'ImportDefaultSpecifier');

            if (defaultSpecifier !== undefined && vueImport.specifiers.length === 1) {
                return fixer.insertTextAfter(defaultSpecifier, ', { nextTick }');
            }

            const anchor = firstImport ?? sourceCode.ast.body[0];

            return anchor === undefined ? null : fixer.insertTextBefore(anchor, "import { nextTick } from 'vue';\n");
        }

        /**
         * Renders a property value at the indentation of the statement that replaces the call.
         *
         * A multi-line value carries the indentation it had one level deeper inside the object literal,
         * which leaves it visibly over-indented once it becomes a top-level assignment. Template literals
         * are left alone: their line starts are content, not layout.
         */
        function renderValue(property, statementIndentation) {
            const text = sourceCode.getText(property.value);

            if (!text.includes('\n') || text.includes('`')) {
                return text;
            }

            const propertyLine = sourceCode.lines[property.loc.start.line - 1];
            const shift = propertyLine.length - propertyLine.trimStart().length - statementIndentation.length;

            if (shift <= 0) {
                return text;
            }

            return text
                .split('\n')
                .map((line, index) =>
                    index === 0 ? line : line.slice(Math.min(shift, line.length - line.trimStart().length)),
                )
                .join('\n');
        }

        function buildFix(fixer, node, classification) {
            const receiver = sourceCode.getText(node.callee.object);
            const localNextTick = nextTickLocalName ?? 'nextTick';

            const assignments = classification.properties.map(
                (property) =>
                    `${receiver}.vm${buildAccessor(property)} = ${renderValue(property, classification.indentation)};`,
            );
            assignments.push(`await ${localNextTick}();`);

            const fixes = [
                fixer.replaceText(classification.statement, assignments.join(`\n${classification.indentation}`)),
            ];
            const importFix = buildNextTickImportFix(fixer);

            if (importFix !== null) {
                fixes.push(importFix);
            }

            return fixes;
        }

        return {
            ImportDeclaration(node) {
                firstImport ??= node;

                if (typeof node.source.value !== 'string') {
                    return;
                }

                if (node.source.value.endsWith(VUE_SUFFIX)) {
                    importsVueFile = true;

                    return;
                }

                if (node.source.value !== 'vue') {
                    return;
                }

                vueImport ??= node;

                const nextTickSpecifier = node.specifiers.find(
                    (specifier) => specifier.type === 'ImportSpecifier' && specifier.imported.name === 'nextTick',
                );

                if (nextTickSpecifier !== undefined) {
                    nextTickLocalName = nextTickSpecifier.local.name;
                }
            },

            ImportExpression(node) {
                if (node.source.type === 'Literal' && String(node.source.value).endsWith(VUE_SUFFIX)) {
                    importsVueFile = true;
                }
            },

            CallExpression(node) {
                if (node.callee.type === 'MemberExpression' && node.callee.property.name === 'setData') {
                    setDataCalls.push(node);
                }
            },

            'Program:exit': function reportCollectedCalls() {
                if (setDataCalls.length === 0 || !mountsNativeSetupComponent()) {
                    return;
                }

                for (const node of setDataCalls) {
                    if (targetsAnUnconvertedComponent(node)) {
                        continue;
                    }

                    const classification = classifySetDataCall(node, sourceCode);

                    if (classification.blocker !== undefined) {
                        context.report({
                            node,
                            messageId: 'silentNoOpManualRewrite',
                            data: { blocker: classification.blocker },
                        });

                        continue;
                    }

                    context.report({
                        node,
                        messageId: 'silentNoOp',
                        data: { receiver: sourceCode.getText(node.callee.object) },
                        fix: (fixer) => buildFix(fixer, node, classification),
                    });
                }
            },
        };
    },
};
