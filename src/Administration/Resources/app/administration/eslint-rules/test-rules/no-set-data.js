/**
 * @sw-package framework
 */

const fs = require('fs');
const path = require('path');

const VUE_SUFFIX = '.vue';
const OVERRIDE_SUFFIX = '.override.vue';
const IGNORED_DIRECTORY_NAMES = new Set([
    'node_modules',
    '.git',
]);

/**
 * Native setup component names mapped to the directories that declare them, keyed by `src` root.
 *
 * Built once per root per ESLint process. The names come from a `readdir` rather than from
 * `test/_helper_/componentWrapper/component-imports.js`, which is generated and git-ignored and so is
 * missing in a fresh checkout.
 */
const componentIndexCache = new Map();

/**
 * Derives the component name a native setup SFC registers under.
 *
 * Mirrors `inferShopwareSetupFromFilename` in build/vue-setup-transform: the name is the filename without
 * its `.vue` suffix, or the directory name for an index file.
 */
function deriveComponentName(vueFilePath) {
    const file = path.basename(vueFilePath);

    if (file === `index${VUE_SUFFIX}`) {
        return path.basename(path.dirname(vueFilePath));
    }

    return file.slice(0, -VUE_SUFFIX.length);
}

/**
 * Indexes every native setup base component below `srcRoot`.
 *
 * Overrides are skipped: an `.override.vue` targets a base component the scan already finds under its
 * own name, and a spec never mounts the override directly.
 */
function indexNativeSetupComponents(srcRoot) {
    const cached = componentIndexCache.get(srcRoot);

    if (cached) {
        return cached;
    }

    const index = new Map();
    const pending = [srcRoot];

    while (pending.length > 0) {
        const directory = pending.pop();
        let entries;

        try {
            entries = fs.readdirSync(directory, { withFileTypes: true });
        } catch {
            continue;
        }

        for (const entry of entries) {
            if (entry.isDirectory()) {
                if (!IGNORED_DIRECTORY_NAMES.has(entry.name)) {
                    pending.push(path.join(directory, entry.name));
                }

                continue;
            }

            if (!entry.name.endsWith(VUE_SUFFIX) || entry.name.endsWith(OVERRIDE_SUFFIX)) {
                continue;
            }

            const componentName = deriveComponentName(path.join(directory, entry.name));

            if (!index.has(componentName)) {
                index.set(componentName, new Set());
            }

            index.get(componentName).add(directory);
        }
    }

    componentIndexCache.set(srcRoot, index);

    return index;
}

/**
 * Walks up from a spec to the `src` directory of the npm package that owns it.
 *
 * Only that one package is indexed, so a spec in a satellite package (the Storefront's Administration
 * extension) does not resolve `wrapTestComponent()` names that live in the core Administration package.
 * Those specs fall back to the `.vue`-import and sibling-file signals.
 */
function findSrcRoot(filename) {
    let directory = path.dirname(filename);

    for (;;) {
        if (fs.existsSync(path.join(directory, 'package.json')) && fs.existsSync(path.join(directory, 'src'))) {
            return path.join(directory, 'src');
        }

        const parent = path.dirname(directory);

        if (parent === directory) {
            return null;
        }

        directory = parent;
    }
}

/**
 * Derives the component a spec belongs to from its own path.
 *
 * `sw-thing/sw-thing.spec.js` and `sw-thing/sw-thing.spec/rendering.spec.js` both belong to `sw-thing`.
 */
function deriveSpecSubject(filename) {
    const directory = path.dirname(filename);
    const specDirectory = path.basename(directory);

    if (specDirectory.endsWith('.spec')) {
        return {
            componentName: specDirectory.slice(0, -'.spec'.length),
            directories: [path.dirname(directory)],
        };
    }

    const file = path.basename(filename);
    const componentName = file.replace(/\.spec\.(js|ts)$/, '');

    return {
        componentName,
        directories: [directory],
    };
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
 * A spec counts as mounting a native setup component when it imports a `.vue` file, when it passes a name
 * to `wrapTestComponent()` that resolves to a `.vue` file, or when the component it is named after is a
 * `.vue` file in its own directory.
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
        const wrappedComponentNames = new Set();
        let importsVueFile = false;
        let vueImport = null;
        let nextTickLocalName = null;
        let firstImport = null;
        let importFixEmitted = false;

        function mountsNativeSetupComponent() {
            if (importsVueFile) {
                return true;
            }

            const srcRoot = findSrcRoot(filename);

            if (srcRoot === null) {
                return false;
            }

            const index = indexNativeSetupComponents(srcRoot);

            for (const componentName of wrappedComponentNames) {
                if (index.has(componentName)) {
                    return true;
                }
            }

            const subject = deriveSpecSubject(filename);
            const declaringDirectories = index.get(subject.componentName);

            return declaringDirectories !== undefined && subject.directories.some((d) => declaringDirectories.has(d));
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
                if (node.callee.type === 'Identifier' && node.callee.name === 'wrapTestComponent') {
                    const [componentName] = node.arguments;

                    if (componentName?.type === 'Literal' && typeof componentName.value === 'string') {
                        wrappedComponentNames.add(componentName.value);
                    }

                    return;
                }

                if (node.callee.type === 'MemberExpression' && node.callee.property.name === 'setData') {
                    setDataCalls.push(node);
                }
            },

            'Program:exit': function reportCollectedCalls() {
                if (setDataCalls.length === 0 || !mountsNativeSetupComponent()) {
                    return;
                }

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
