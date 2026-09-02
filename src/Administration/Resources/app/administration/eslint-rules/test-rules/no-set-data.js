/**
 * @sw-package framework
 */

function isIdentifierLike(name) {
    return /^[A-Za-z_$][A-Za-z0-9_$]*$/.test(name);
}

function buildAccessor(property) {
    const key = property.key.type === 'Identifier' ? property.key.name : property.key.value;

    return isIdentifierLike(key) ? `.${key}` : `[${JSON.stringify(key)}]`;
}

/**
 * Reads the object literal as one assignment per key.
 *
 * A nested object literal is refused rather than flattened into `vm.a.b = …`. `mergeDeep` merges into the
 * existing value only when there already is one - against a `null` or absent target it assigns the whole
 * object - so the path form throws wherever the state starts out empty, which lint cannot see. Rewriting
 * the 201 nested calls in the Administration suite that way broke 71 tests across 29 spec files.
 */
function readAssignments(objectExpression) {
    const assignments = [];

    for (const property of objectExpression.properties) {
        if (property.type !== 'Property' || property.kind !== 'init' || property.method) {
            return { blocker: 'the object literal does not spell out plain key/value pairs' };
        }

        if (property.computed || (property.key.type !== 'Identifier' && typeof property.key.value !== 'string')) {
            return { blocker: 'a computed key hides which state is written' };
        }

        if (property.value.type === 'ObjectExpression') {
            return { blocker: 'a nested object literal merges into the existing value instead of replacing it' };
        }

        // `setData` reaches `$data.$refs` because it writes the object directly; `vm.$refs = …` is refused
        // by Vue's proxy, which reserves every `$` name.
        if (String(property.key.name ?? property.key.value).startsWith('$')) {
            return { blocker: 'a $-prefixed key is a reserved Vue property that cannot be assigned' };
        }

        assignments.push({ accessor: buildAccessor(property), property });
    }

    return { assignments };
}

/**
 * Decides whether a `setData` call can be rewritten mechanically, and why not when it cannot.
 *
 * The rewrite reproduces what the spec author meant - assign this state to this value - rather than
 * translating `mergeDeep` faithfully. That reading only holds where the object literal spells its keys
 * out, so everything else is reported without a fix.
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

    const read = readAssignments(argument);

    if (read.blocker !== undefined) {
        return { blocker: read.blocker };
    }

    return { statement, indentation, assignments: read.assignments };
}

/**
 * Reports every `wrapper.setData()` in a spec.
 *
 * `setData` is `mergeDeep(vm.$data, data)`. A native setup component keeps its state in `setupState`, and
 * nothing bridges the two, so the write lands in an object the component never reads. It does not throw
 * either: the global Meteor SDK mixin gives every component a real `data()`, so `$data` is never the
 * frozen `EMPTY_OBJ` whose mutation would have raised. The spec then fails on a later assertion, or keeps
 * passing while asserting nothing.
 *
 * The ban is unconditional rather than scoped to components that are already `.vue` files. Every
 * Administration component is on its way to native setup, so a scoped rule only decides which specs go
 * quiet first, and deciding that statically means guessing which component a wrapper holds. Assigning
 * through `wrapper.vm` works on an Options API component too, so the rewrite is safe wherever it lands.
 *
 * @type {import('eslint').Rule.RuleModule}
 */
module.exports = {
    meta: {
        type: 'problem',
        docs: {
            description: 'Disallow wrapper.setData(), which writes state a component may never read',
            category: 'Possible Errors',
            recommended: true,
        },
        fixable: 'code',
        schema: [],
        messages: {
            silentNoOp:
                'setData() merges into $data, which a native setup component never reads, so the write is ' +
                'discarded the day this component is converted. Assign the state directly instead: ' +
                '`{{ receiver }}.vm.<key> = <value>`, followed by `await nextTick()`.',
            silentNoOpManualRewrite:
                'setData() merges into $data, which a native setup component never reads, so the write is ' +
                'discarded the day this component is converted. Rewrite it as direct assignments to `.vm`, ' +
                'followed by `await nextTick()`. It cannot be rewritten automatically because {{ blocker }}.',
        },
    },

    create(context) {
        const sourceCode = context.sourceCode ?? context.getSourceCode();

        const setDataCalls = [];
        let vueImport = null;
        let nextTickLocalName = null;
        let firstImport = null;
        let importFixEmitted = false;

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

            const assignments = classification.assignments.map(
                ({ accessor, property }) =>
                    `${receiver}.vm${accessor} = ${renderValue(property, classification.indentation)};`,
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

            CallExpression(node) {
                if (node.callee.type === 'MemberExpression' && node.callee.property.name === 'setData') {
                    setDataCalls.push(node);
                }
            },

            'Program:exit': function reportCollectedCalls() {
                for (const node of setDataCalls) {
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
