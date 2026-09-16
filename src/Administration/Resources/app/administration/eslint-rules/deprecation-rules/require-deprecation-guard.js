/**
 * @sw-package framework
 */

const TAG_PATTERN = /@deprecated\s+tag:v(\d+)\.(\d+)\.(\d+)(?:\.(\d+))?/;
const STATIC_ONLY_PATTERN = /@deprecationGuard\s+static-only\s*-\s*\S/;
const GUARD_NAME = 'triggerDeprecationOrThrow';

/**
 * Vue options whose members are reachable from a component but have no safe generic use boundary, so
 * the ADR classifies them as static-only.
 */
const STATIC_ONLY_SECTIONS = [
    'data',
    'watch',
    'provide',
    'inject',
    'emits',
    'components',
    'mixins',
    'state',
    'getters',
];

const MEMBER_SECTIONS = [
    'methods',
    'computed',
    'actions',
];

function toMajorFlag(match) {
    const segments = [
        match[1],
        match[2],
        match[3],
        match[4] ?? '0',
    ];

    return `V${segments.join('_')}`;
}

function isStaticOnlyFile(filename) {
    return /\.spec\.[jt]sx?$/.test(filename) || filename.includes('.spec/') || filename.includes('/test/');
}

const KNOWN_SECTIONS = [
    ...STATIC_ONLY_SECTIONS,
    ...MEMBER_SECTIONS,
    'props',
];

/**
 * The Vue option the declaration lives under — `methods`, `data`, `props` and so on. It decides whether
 * a guard is required at all, so only the nearest enclosing option counts: a computed property may
 * itself be called `data` without becoming static-only.
 */
function governingSection(node) {
    let current = node.type === 'Property' ? node.parent : node;

    while (current) {
        if (current.type === 'Property' && current.key) {
            const name = current.key.name ?? current.key.value;

            if (KNOWN_SECTIONS.includes(name)) {
                return name;
            }
        }

        current = current.parent;
    }

    return '';
}

/**
 * The `methods` / `computed` / `actions` member a node sits inside. A marker often sits on a single
 * statement or argument deep in a member — the member is still the boundary that gets the guard.
 */
function enclosingMember(node, section) {
    let current = node;

    while (current?.parent) {
        const parent = current.parent;

        if (
            current.type === 'Property' &&
            parent.type === 'ObjectExpression' &&
            parent.parent?.type === 'Property' &&
            (parent.parent.key?.name ?? parent.parent.key?.value) === section
        ) {
            return current;
        }

        current = parent;
    }

    return null;
}

/**
 * Types, interfaces and type annotations are static-only, and a marker can sit deep inside one — on a
 * single member of a union, for instance.
 */
function isInTypeContext(node) {
    let current = node;

    while (current) {
        if (current.type.startsWith('TS') && current.type !== 'TSNonNullExpression') {
            return true;
        }

        current = current.parent;
    }

    return false;
}

function propertyName(node) {
    if (node.type === 'Property') {
        return node.key.name ?? String(node.key.value);
    }

    if (node.type === 'FunctionDeclaration' || node.type === 'ClassDeclaration') {
        return node.id?.name ?? '';
    }

    if (node.type === 'VariableDeclaration') {
        return node.declarations[0]?.id?.name ?? '';
    }

    if (node.type === 'MethodDefinition' || node.type === 'PropertyDefinition') {
        return node.key.name ?? String(node.key.value);
    }

    return '';
}

/**
 * Every function body reachable from the declaration that a guard could sit in. A computed written as
 * an accessor object contributes both `get` and `set`, because both are a use of the deprecated member.
 */
function guardableBodies(node) {
    const valueOf = (candidate) => {
        if (candidate.type === 'Property') {
            return candidate.value;
        }

        if (candidate.type === 'MethodDefinition') {
            return candidate.value;
        }

        if (candidate.type === 'VariableDeclaration') {
            return candidate.declarations[0]?.init;
        }

        // `this.app.config.globalProperties.$tc = function () { … }` — the guard lives in the
        // assigned function, not in the statement the comment sits on.
        if (candidate.type === 'ExpressionStatement' && candidate.expression.type === 'AssignmentExpression') {
            return candidate.expression.right;
        }

        return candidate;
    };

    let value = valueOf(node);

    // `function () { … } as typeof x` — look through the type assertion to the function itself.
    while (value && (value.type === 'TSAsExpression' || value.type === 'TSSatisfiesExpression')) {
        value = value.expression;
    }

    if (!value) {
        return [];
    }

    if (value.type === 'ClassDeclaration' || value.type === 'ClassExpression') {
        const constructor = value.body.body.find(
            (member) => member.type === 'MethodDefinition' && member.kind === 'constructor',
        );

        return constructor ? [constructor.value] : [];
    }

    if (
        value.type === 'FunctionExpression' ||
        value.type === 'ArrowFunctionExpression' ||
        value.type === 'FunctionDeclaration'
    ) {
        return [value];
    }

    // `Utils.debounce(function () { … })` and friends: the guard sits in the wrapped function.
    if (value.type === 'CallExpression') {
        return value.arguments.filter(
            (argument) => argument.type === 'FunctionExpression' || argument.type === 'ArrowFunctionExpression',
        );
    }

    if (value.type === 'ObjectExpression') {
        const accessors = value.properties.filter(
            (property) =>
                property.type === 'Property' &&
                [
                    'get',
                    'set',
                ].includes(property.key?.name),
        );

        return accessors.map((accessor) => accessor.value);
    }

    return [];
}

function hasGuardCall(sourceCode, body, expectedFlag) {
    const text = sourceCode.getText(body);

    if (!text.includes(GUARD_NAME)) {
        return { guarded: false, flagMatches: false };
    }

    const flags = [...text.matchAll(new RegExp(`${GUARD_NAME}\\(\\s*'([^']+)'`, 'g'))].map((match) => match[1]);

    return {
        guarded: true,
        flagMatches: flags.some((flag) => flag.toUpperCase() === expectedFlag),
    };
}

function hasDeprecatedOption(node) {
    const object =
        node.type === 'Property' && node.value.type === 'ObjectExpression'
            ? node.value
            : node.type === 'ObjectExpression'
              ? node
              : null;

    if (!object) {
        return false;
    }

    return object.properties.some((property) => property.type === 'Property' && property.key?.name === 'deprecated');
}

/**
 * The component config object of `export default { ... }`, `export default wrapComponentConfig({ ... })`
 * and the `Shopware.Component.wrapComponentConfig` variant.
 */
function componentConfigOf(node) {
    if (node.type !== 'ExportDefaultDeclaration') {
        return null;
    }

    const declaration = node.declaration;

    if (declaration.type === 'ObjectExpression') {
        return declaration;
    }

    if (declaration.type === 'CallExpression' && declaration.arguments[0]?.type === 'ObjectExpression') {
        return declaration.arguments[0];
    }

    return null;
}

/**
 * This rule enforces the runtime side of adr/2026-08-10-administration-javascript-deprecation-guards.md:
 * a public, runtime-detectable `@deprecated tag:vX.Y.0` symbol has to guard its own use boundary with
 * `Feature.triggerDeprecationOrThrow(...)`, so that legacy use warns before the major and fails once
 * the major flag is active.
 *
 * Skipped without complaint: `@private` symbols, identifiers starting with `_`, types and interfaces,
 * spec files, and the Vue options the ADR classifies as static-only (`data`, `watch`, `provide`,
 * `inject`, `emits`, `components`, `mixins`, store `state` and `getters`).
 *
 * Everything else that cannot carry a guard needs to say why:
 *
 *     \**
 *      * @deprecated tag:v6.8.0 - Will be removed
 *      * @deprecationGuard static-only - Module-level import, there is no runtime use boundary.
 *      *\
 */
/** @type {import('eslint').Rule.RuleModule} */
module.exports = {
    meta: {
        type: 'problem',

        docs: {
            description: 'Deprecated public Administration APIs must guard their own use boundary',
            recommended: true,
            url: 'https://github.com/shopware/shopware/blob/trunk/adr/2026-08-10-administration-javascript-deprecation-guards.md',
        },

        schema: [],

        messages: {
            missingGuard:
                'The deprecated "{{name}}" has no runtime guard. Call ' +
                "Shopware.Feature.triggerDeprecationOrThrow('{{flag}}', '...') as the first statement of its " +
                'body, or state why it cannot be guarded with "@deprecationGuard static-only - <reason>".',
            missingComponentOption:
                'The deprecated component has no runtime guard. Add a root "deprecated: { version: ' +
                "'{{version}}', comment: '...' }\" option so the deprecation plugin guards it at creation, or " +
                'state why it cannot be guarded with "@deprecationGuard static-only - <reason>".',
            missingPropOption:
                'The deprecated prop "{{name}}" has no runtime guard. Add "deprecated: { version: ' +
                "'{{version}}', comment: '...' }\" to its definition so the deprecation plugin guards it when the " +
                'prop is supplied, or state why it cannot be guarded with "@deprecationGuard static-only - <reason>".',
            flagMismatch:
                'The guard of the deprecated "{{name}}" does not use the major flag "{{flag}}" that ' +
                '"@deprecated tag:{{version}}" announces.',
            unguardableSymbol:
                'The deprecated "{{name}}" is not a shape this rule can require a guard on. State why it ' +
                'stays static with "@deprecationGuard static-only - <reason>".',
        },
    },

    create(context) {
        const sourceCode = context.sourceCode ?? context.getSourceCode();
        const filename = context.filename ?? context.getFilename();

        if (isStaticOnlyFile(filename)) {
            return {};
        }

        /**
         * The declaration a deprecation comment belongs to: the first node that starts after the
         * comment and is not itself a comment.
         */
        function declarationAfter(comment) {
            const token = sourceCode.getTokenAfter(comment, { includeComments: false });

            if (!token) {
                return null;
            }

            let node = sourceCode.getNodeByRangeIndex(token.range[0]);

            // Widen to the whole declaration the token opens — `oldMethod` to its `Property`,
            // `export` to its `ExportDefaultDeclaration`. `Program` shares the start offset of its
            // first declaration, so it has to be excluded explicitly.
            while (
                node?.parent &&
                node.parent.type !== 'Program' &&
                node.parent.range[0] === node.range[0] &&
                ![
                    'Property',
                    'MethodDefinition',
                    'PropertyDefinition',
                ].includes(node.type)
            ) {
                node = node.parent;
            }

            if (node?.type === 'ExportNamedDeclaration' && node.declaration) {
                return node.declaration;
            }

            return node;
        }

        const comments = sourceCode.getAllComments();

        /**
         * A run of `//` lines reads as one annotation, so `@deprecated` on one line and
         * `@deprecationGuard` on the next belong to the same block. Block comments stand alone.
         */
        function commentBlock(comment) {
            if (comment.type !== 'Line') {
                return [comment];
            }

            const index = comments.indexOf(comment);
            const block = [comment];

            for (let i = index - 1; i >= 0; i -= 1) {
                if (comments[i].type !== 'Line' || comments[i].loc.end.line !== block[0].loc.start.line - 1) {
                    break;
                }

                block.unshift(comments[i]);
            }

            for (let i = index + 1; i < comments.length; i += 1) {
                if (comments[i].type !== 'Line' || comments[i].loc.start.line !== block[block.length - 1].loc.end.line + 1) {
                    break;
                }

                block.push(comments[i]);
            }

            return block;
        }

        function check(comment) {
            const tag = TAG_PATTERN.exec(comment.value);

            if (!tag) {
                return;
            }

            const block = commentBlock(comment);
            const blockText = block.map((entry) => entry.value).join('\n');

            if (blockText.includes('@private') || STATIC_ONLY_PATTERN.test(blockText)) {
                return;
            }

            const node = declarationAfter(block[block.length - 1]);

            if (!node) {
                return;
            }

            const flag = toMajorFlag(tag);
            const version = `v${tag[1]}.${tag[2]}.${tag[3]}.${tag[4] ?? '0'}`;
            const name = propertyName(node);

            if (name.startsWith('_')) {
                return;
            }

            if (node.type === 'ImportDeclaration' || isInTypeContext(node)) {
                return;
            }

            const section = governingSection(node);

            if (STATIC_ONLY_SECTIONS.includes(section)) {
                return;
            }

            const componentConfig = componentConfigOf(node);

            if (componentConfig) {
                if (!hasDeprecatedOption(componentConfig)) {
                    context.report({ node, messageId: 'missingComponentOption', data: { version } });
                }

                return;
            }

            if (section === 'props') {
                if (!hasDeprecatedOption(node)) {
                    context.report({ node, messageId: 'missingPropOption', data: { name, version } });
                }

                return;
            }

            let guarded = node;
            let bodies = guardableBodies(node);

            if (bodies.length === 0 && MEMBER_SECTIONS.includes(section)) {
                const member = enclosingMember(node, section);

                if (member) {
                    guarded = member;
                    bodies = guardableBodies(member);
                }
            }

            if (bodies.length === 0) {
                const isMember = MEMBER_SECTIONS.includes(section);

                context.report({
                    node,
                    messageId: isMember ? 'missingGuard' : 'unguardableSymbol',
                    data: { name, flag },
                });

                return;
            }

            const guardedName = name || propertyName(guarded);

            bodies.forEach((body) => {
                const { guarded: hasGuard, flagMatches } = hasGuardCall(sourceCode, body, flag);

                if (!hasGuard) {
                    context.report({ node: body, messageId: 'missingGuard', data: { name: guardedName, flag } });

                    return;
                }

                if (!flagMatches) {
                    context.report({
                        node: body,
                        messageId: 'flagMismatch',
                        data: { name: guardedName, flag, version },
                    });
                }
            });
        }

        return {
            Program() {
                sourceCode.getAllComments().forEach(check);
            },
        };
    },
};
