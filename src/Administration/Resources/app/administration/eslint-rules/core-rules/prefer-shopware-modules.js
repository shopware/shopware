/**
 * @sw-package framework
 *
 * Reports reads of the global `Shopware` object that a `shopware:*` import covers, and rewrites them.
 *
 * The policy is `scripts/codemods/shopware-virtual-modules`, which did the one-time sweep; this rule is
 * the standing guard that keeps new code from reintroducing the old style. Both decide what exists from
 * `shopware-modules.json`, so neither can name an export the generated module does not have.
 *
 * Administration-only while the modules are experimental. They become stable with 6.8, and this rule
 * should then be given to extensions as well: add it to the `sw-core-rules` block in
 * `extension-tooling/eslint.mjs`, which is the list of core rules an extension program receives.
 *
 * Which files are exempt is not decided here. `src/core`, specs and the modules evaluated before
 * `window.Shopware` exists are excluded by the ESLint config, which can compute that set; this file is
 * plain CommonJS and ESLint loads it outside any TypeScript runtime.
 */

const path = require('path');

const REGISTRY_FILE = path.resolve(__dirname, '../../shopware-modules.json');

/** The branch each `Shopware.<name>` property belongs to. Mirrors FAMILY_BY_BRANCH in the codemod. */
const FAMILY_BY_BRANCH = {
    Utils: 'shopware:utils',
    Data: 'shopware:data',
};

/** The registry lookups that collapse into a subpath import, by the branch and method that spell them. */
const LOOKUPS = {
    'Mixin.getByName': { family: 'shopware:mixins', declaredIn: 'MixinContainer' },
    'Store.get': { family: 'shopware:stores', declaredIn: 'PiniaRootState' },
};

function upperFirst(value) {
    return value.charAt(0).toUpperCase() + value.slice(1);
}

function camelCase(value) {
    const [
        head = '',
        ...rest
    ] = value.split('-').filter((part) => part.length > 0);

    return `${head}${rest.map(upperFirst).join('')}`;
}

/** `sw-form-field` becomes `swFormFieldMixin`, matching the codemod so one file cannot get both names. */
function mixinLocalName(mixinName) {
    return `${camelCase(mixinName)}Mixin`;
}

/** `swOrderDetail` becomes `useSwOrderDetailStore`, Pinia's convention. */
function storeLocalName(storeId) {
    return `use${upperFirst(storeId)}Store`;
}

/** `Shopware.Utils` and friends, as `<branch>.<member>`, or undefined for anything else. */
function branchAccess(node) {
    if (node.type !== 'MemberExpression' || node.computed) {
        return undefined;
    }

    const branch = node.object;

    if (branch.type !== 'MemberExpression' || branch.computed) {
        return undefined;
    }

    if (branch.object.type !== 'Identifier' || branch.object.name !== 'Shopware') {
        return undefined;
    }

    return { branch: branch.property.name, member: node.property.name };
}

/**
 * The specifier a destructuring initialiser stands for, and where its names must be declared.
 *
 *     const { Criteria } = Shopware.Data;          → shopware:data, names from that family's exports
 *     const { warn } = Shopware.Utils.debug;       → shopware:utils/debug, names from that subpath
 */
function destructuringTarget(init, registry) {
    if (init.type !== 'MemberExpression' || init.computed) {
        return undefined;
    }

    if (init.object.type === 'Identifier' && init.object.name === 'Shopware') {
        const family = FAMILY_BY_BRANCH[init.property.name];

        return family ? { specifier: family, names: registry[family].exports } : undefined;
    }

    const access = branchAccess(init);
    const family = access && FAMILY_BY_BRANCH[access.branch];
    const names = family && registry[family].subpaths[access.member];

    return names && names.length > 0 ? { specifier: `${family}/${access.member}`, names } : undefined;
}

/** The single string argument of the call this node heads, or undefined when the key is not a literal. */
function literalKeyOf(node) {
    const call = node.parent;

    if (!call || call.type !== 'CallExpression' || call.callee !== node || call.arguments.length !== 1) {
        return undefined;
    }

    const [argument] = call.arguments;

    return argument.type === 'Literal' && typeof argument.value === 'string' ? argument.value : undefined;
}

module.exports = {
    meta: {
        type: 'suggestion',
        docs: {
            description: 'Prefer the shopware:* modules over reads of the global Shopware object',
            category: 'Best Practices',
            recommended: false,
        },
        fixable: 'code',
        schema: [],
        messages: {
            preferModule: "Import '{{ specifier }}' instead of reading {{ expression }} off the global.",
        },
    },

    create(context) {
        const registry = require(REGISTRY_FILE);
        const source = context.sourceCode ?? context.getSourceCode();

        /** Names the file already binds. A rewrite that would shadow one is left alone, as the codemod leaves it. */
        const bound = new Set();
        /** Import declarations by specifier, so a second rewrite reuses the one the first added. */
        const imports = new Map();
        const reported = new Set();
        /** Initialisers a destructuring already owns, so the member visitor does not rewrite them too. */
        const claimed = new Set();
        /**
         * Imports an earlier fix in this pass already adds.
         *
         * Every occurrence needs the same import, but two fixes inserting it at the same position collide
         * and ESLint drops one of them per pass. A file reading `Criteria` seventeen times would then need
         * seventeen passes and never finish, so only the first fix carries the import.
         */
        const importPlanned = new Set();

        function collectBindings(program) {
            const scope = source.getScope ? source.getScope(program) : context.getScope();

            scope.variables.forEach((variable) => bound.add(variable.name));
            scope.childScopes
                .filter((child) => child.type === 'module')
                .forEach((child) => child.variables.forEach((variable) => bound.add(variable.name)));

            program.body
                .filter((statement) => statement.type === 'ImportDeclaration')
                .forEach((statement) => imports.set(statement.source.value, statement));
        }

        /** The edit that makes `local` available, or null when the file already imports it. */
        function importFix(fixer, specifier, local, imported) {
            const existing = imports.get(specifier);

            if (existing) {
                const alreadyThere = existing.specifiers.some((one) => one.local.name === local);

                if (alreadyThere) {
                    return null;
                }

                const last = existing.specifiers[existing.specifiers.length - 1];

                return imported === undefined
                    ? fixer.insertTextBefore(last, `${local}, `)
                    : fixer.insertTextAfter(last, `, ${imported === local ? local : `${imported} as ${local}`}`);
            }

            const clause = imported === undefined ? local : `{ ${imported === local ? local : `${imported} as ${local}`} }`;
            const [first] = source.ast.body;

            // Always above the first statement. Chaining onto the last import instead would follow an
            // import this same pass had just written into the middle of the file.
            return fixer.insertTextBefore(first, `import ${clause} from '${specifier}';\n`);
        }

        function report(node, target, specifier, local, imported, replacement) {
            if (bound.has(local) || reported.has(target)) {
                return;
            }

            reported.add(target);

            context.report({
                node: target,
                messageId: 'preferModule',
                data: { specifier, expression: source.getText(node) },
                fix(fixer) {
                    const key = `${specifier}|${local}`;
                    const edits = [fixer.replaceText(target, replacement)];

                    if (!importPlanned.has(key)) {
                        const addImport = importFix(fixer, specifier, local, imported);

                        importPlanned.add(key);

                        if (addImport) {
                            // Ahead of the replacement, so the import can never arrive without its use.
                            edits.unshift(addImport);
                        }
                    }

                    return edits;
                },
            });
        }

        return {
            Program: collectBindings,

            VariableDeclarator(node) {
                if (node.id.type !== 'ObjectPattern' || !node.init) {
                    return;
                }

                const target = destructuringTarget(node.init, registry);

                if (!target) {
                    return;
                }

                const names = node.id.properties.map((property) =>
                    property.type === 'Property' && !property.computed && property.value.type === 'Identifier'
                        ? { imported: property.key.name, local: property.value.name }
                        : undefined,
                );

                // All or nothing: a rest element or a default leaves a binding the import cannot express.
                if (names.some((one) => one === undefined || !target.names.includes(one.imported))) {
                    return;
                }

                // Claimed, so the MemberExpression visitor leaves the initialiser alone.
                claimed.add(node.init);

                const clause = names
                    .map((one) => (one.imported === one.local ? one.local : `${one.imported} as ${one.local}`))
                    .join(', ');
                const declaration = node.parent;

                context.report({
                    node: declaration,
                    messageId: 'preferModule',
                    data: { specifier: target.specifier, expression: source.getText(node.init) },
                    fix(fixer) {
                        const [first] = source.ast.body;
                        const statement = `import { ${clause} } from '${target.specifier}';\n`;

                        // The declaration goes away entirely; its names arrive as the import instead.
                        if (declaration === first) {
                            return [fixer.replaceText(declaration, statement.trimEnd())];
                        }

                        // From the start of the line, so the indentation the statement sat on goes with
                        // it rather than prefixing the next one.
                        const text = source.getText();
                        const lineStart = text.lastIndexOf('\n', declaration.range[0] - 1) + 1;
                        const indentOnly = text.slice(lineStart, declaration.range[0]).trim() === '';

                        return [
                            fixer.insertTextBefore(first, statement),
                            fixer.removeRange([
                                indentOnly ? lineStart : declaration.range[0],
                                declaration.range[1] + 1,
                            ]),
                        ];
                    },
                });
            },

            MemberExpression(node) {
                if (claimed.has(node)) {
                    return;
                }

                const access = branchAccess(node);

                if (!access) {
                    return;
                }

                const lookup = LOOKUPS[`${access.branch}.${access.member}`];

                if (lookup) {
                    const key = literalKeyOf(node);

                    // A key only known at runtime names no specifier, so the read has to stay.
                    if (key === undefined || !(key in registry[lookup.family].subpaths)) {
                        return;
                    }

                    const isStore = lookup.family === 'shopware:stores';
                    const local = isStore ? storeLocalName(key) : mixinLocalName(key);

                    report(
                        node.parent,
                        node.parent,
                        `${lookup.family}/${key}`,
                        local,
                        undefined,
                        isStore ? `${local}()` : local,
                    );

                    return;
                }

                const family = FAMILY_BY_BRANCH[access.branch];

                if (family && registry[family].exports.includes(access.member)) {
                    report(node, node, family, access.member, access.member, access.member);
                }
            },
        };
    },
};
