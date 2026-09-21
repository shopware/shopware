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

/** The branch halves of LOOKUPS, so an alias of one is recognised as a branch worth following. */
const LOOKUP_BRANCHES = new Set(Object.keys(LOOKUPS).map((key) => key.split('.')[0]));

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

/**
 * The branch and member a `<branch>.<member>` access names, or undefined for anything else.
 *
 * Two spellings reach the same branch, and the second is why `aliases` exists:
 *
 *     Shopware.Mixin.getByName('notification')
 *     const { Mixin } = Shopware;  …  Mixin.getByName('notification')
 *
 * In the second the call site never mentions `Shopware`, so the branch is only knowable by following
 * the binding — which is what the caller passes in.
 */
function branchAccess(node, aliases) {
    if (node.type !== 'MemberExpression' || node.computed) {
        return undefined;
    }

    const object = node.object;
    const member = node.property.name;

    if (
        object.type === 'MemberExpression' &&
        !object.computed &&
        object.object.type === 'Identifier' &&
        object.object.name === 'Shopware'
    ) {
        return { branch: object.property.name, member };
    }

    const aliased = object.type === 'Identifier' && aliases && aliases.get(object.name);

    return aliased ? { branch: aliased.branch, member, alias: object.name } : undefined;
}

/**
 * The specifier a destructuring initialiser stands for, and where its names must be declared.
 *
 *     const { Criteria } = Shopware.Data;          → shopware:data, names from that family's exports
 *     const { warn } = Shopware.Utils.debug;       → shopware:utils/debug, names from that subpath
 */
function destructuringTarget(init, registry, aliases) {
    // `const { Criteria } = Data;`, where Data is the branch destructured earlier.
    if (init.type === 'Identifier' && aliases && aliases.has(init.name)) {
        const family = FAMILY_BY_BRANCH[aliases.get(init.name).branch];

        return family ? { specifier: family, names: registry[family].exports } : undefined;
    }

    if (init.type !== 'MemberExpression' || init.computed) {
        return undefined;
    }

    if (init.object.type === 'Identifier' && init.object.name === 'Shopware') {
        const family = FAMILY_BY_BRANCH[init.property.name];

        return family ? { specifier: family, names: registry[family].exports } : undefined;
    }

    const access = branchAccess(init, aliases);
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

/** Depth-first walk over the AST, visiting every node object under `root`. */
function walk(root, visit) {
    const seen = new Set();

    const step = (node) => {
        if (!node || typeof node !== 'object' || seen.has(node)) {
            return;
        }

        seen.add(node);

        if (Array.isArray(node)) {
            node.forEach(step);

            return;
        }

        if (typeof node.type === 'string') {
            visit(node);
        }

        Object.entries(node).forEach(([key, value]) => {
            if (key !== 'parent' && value && typeof value === 'object') {
                step(value);
            }
        });
    };

    step(root);
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

        /** Names the file already binds. A rewrite that would shadow one is left alone. */
        const bound = new Set();
        /** Import declarations by specifier, so a rewrite can merge into one the file already has. */
        const imports = new Map();
        /** Local name -> { branch, property, declarator } for `const { Mixin } = Shopware;`. */
        const aliases = new Map();
        /** Every rewrite this file needs, in source order, keyed by the node that carries it. */
        const plans = new Map();
        /** Aliases every reader of which is rewritten, so the binding goes too. */
        const dead = new Set();
        /** Initialisers a destructuring already owns, so the member planner does not claim them too. */
        const claimed = new Set();

        /**
         * The whole file is planned before a single problem is reported.
         *
         * ESLint applies one pass of non-overlapping fixes, and every rewrite in a file wants its import
         * at the same spot — the top. Left to themselves those insertions collide and all but one are
         * dropped, so a file needing four imports would take four passes, and an alias removed alongside
         * the first rewrite would strand the other three. Planning up front lets the first problem carry
         * every import and every alias removal at once, while the rest carry only their own replacement.
         */
        function plan(program) {
            const scope = source.getScope ? source.getScope(program) : context.getScope();

            scope.variables.forEach((variable) => bound.add(variable.name));
            scope.childScopes
                .filter((child) => child.type === 'module')
                .forEach((child) => child.variables.forEach((variable) => bound.add(variable.name)));

            program.body
                .filter((statement) => statement.type === 'ImportDeclaration')
                .forEach((statement) => imports.set(statement.source.value, statement));

            collectAliases(program);

            const aliasUses = new Map();

            const countUse = (name) => {
                const seen = aliasUses.get(name) ?? { total: 0, covered: 0 };

                seen.total += 1;
                aliasUses.set(name, seen);
            };

            walk(program, (node) => {
                if (node.type === 'VariableDeclarator') {
                    // `const { get, format } = Utils;` reads the alias without a member expression.
                    if (node.init && node.init.type === 'Identifier' && aliases.has(node.init.name)) {
                        countUse(node.init.name);
                    }

                    planDestructuring(node);
                }

                if (node.type !== 'MemberExpression') {
                    return;
                }

                // Counted before the claimed check: `const { isEmpty } = Utils.types;` reads the alias
                // even though the destructuring planner owns the expression.
                if (node.object.type === 'Identifier' && aliases.has(node.object.name)) {
                    countUse(node.object.name);
                }

                if (!claimed.has(node)) {
                    planMember(node);
                }
            });

            // Only now is it known which uses are actually rewritten, guards included.
            plans.forEach((entry) => {
                if (!entry.alias) {
                    return;
                }

                const seen = aliasUses.get(entry.alias);

                if (seen) {
                    seen.covered += 1;
                }
            });

            aliasUses.forEach((seen, name) => {
                if (seen.total > 0 && seen.total === seen.covered) {
                    dead.add(name);
                }
            });
        }

        /** Records every `const { <branch> } = Shopware;` at the top level of the module. */
        function collectAliases(program) {
            program.body
                .filter((statement) => statement.type === 'VariableDeclaration')
                .forEach((statement) =>
                    statement.declarations.forEach((declarator) => {
                        if (
                            declarator.id.type !== 'ObjectPattern' ||
                            !declarator.init ||
                            declarator.init.type !== 'Identifier' ||
                            declarator.init.name !== 'Shopware'
                        ) {
                            return;
                        }

                        declarator.id.properties.forEach((property) => {
                            if (
                                property.type !== 'Property' ||
                                property.computed ||
                                property.value.type !== 'Identifier' ||
                                !(FAMILY_BY_BRANCH[property.key.name] || LOOKUP_BRANCHES.has(property.key.name))
                            ) {
                                return;
                            }

                            aliases.set(property.value.name, { branch: property.key.name, property, declarator });
                        });
                    }),
                );
        }

        /**
         * What one `<branch>.<member>` access becomes, or undefined when no specifier covers it.
         *
         * `Shopware.Store.get(id)` with a non-literal id, and any member the registry does not list, both
         * fall through here and keep their alias alive.
         */
        function rewriteFor(node, access) {
            const lookup = LOOKUPS[`${access.branch}.${access.member}`];

            if (lookup) {
                const key = literalKeyOf(node);

                if (key === undefined || !(key in registry[lookup.family].subpaths)) {
                    return undefined;
                }

                const isStore = lookup.family === 'shopware:stores';
                const local = isStore ? storeLocalName(key) : mixinLocalName(key);

                return {
                    node: node.parent,
                    target: node.parent,
                    specifier: `${lookup.family}/${key}`,
                    local,
                    imported: undefined,
                    replacement: isStore ? `${local}()` : local,
                    alias: access.alias,
                };
            }

            const family = FAMILY_BY_BRANCH[access.branch];

            if (!family || !registry[family].exports.includes(access.member)) {
                return undefined;
            }

            return {
                node,
                target: node,
                specifier: family,
                local: access.member,
                imported: access.member,
                replacement: access.member,
                alias: access.alias,
            };
        }

        function planMember(node) {
            const access = branchAccess(node, aliases);
            const entry = access && rewriteFor(node, access);

            if (!entry || bound.has(entry.local) || plans.has(entry.target)) {
                return;
            }

            plans.set(entry.target, entry);
        }

        /** `const { Criteria } = Shopware.Data;` and friends: the statement becomes the import. */
        function planDestructuring(node) {
            if (node.id.type !== 'ObjectPattern' || !node.init) {
                return;
            }

            const target = destructuringTarget(node.init, registry, aliases);

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

            claimed.add(node.init);
            plans.set(node.parent, {
                kind: 'destructuring',
                node: node.init,
                target: node.parent,
                specifier: target.specifier,
                names,
                alias: aliasReadBy(node.init),
            });
        }

        /** The alias an initialiser reads, for `Utils` and for `Utils.types` alike. */
        function aliasReadBy(init) {
            if (init.type === 'Identifier' && aliases.has(init.name)) {
                return init.name;
            }

            if (init.type === 'MemberExpression' && init.object.type === 'Identifier' && aliases.has(init.object.name)) {
                return init.object.name;
            }

            return undefined;
        }

        /** Every import the planned rewrites need, deduplicated, in the order they were planned. */
        function plannedImports() {
            const wanted = new Map();

            plans.forEach((entry) => {
                if (entry.kind === 'destructuring') {
                    entry.names.forEach((one) =>
                        wanted.set(`${entry.specifier}|${one.local}`, {
                            specifier: entry.specifier,
                            local: one.local,
                            imported: one.imported,
                        }),
                    );

                    return;
                }

                wanted.set(`${entry.specifier}|${entry.local}`, entry);
            });

            return [...wanted.values()];
        }

        /**
         * The import statements a set of planned imports needs, one per specifier.
         *
         * Two names from one specifier belong on one line: `import { Criteria, EntityCollection } from
         * 'shopware:data';`, the shape the destructuring they replace already had.
         */
        function importStatements(wanted) {
            const bySpecifier = new Map();

            wanted.forEach((one) => {
                const group = bySpecifier.get(one.specifier) ?? { named: [], default: undefined };

                if (one.imported === undefined) {
                    group.default = one.local;
                } else {
                    group.named.push(one.imported === one.local ? one.local : `${one.imported} as ${one.local}`);
                }

                bySpecifier.set(one.specifier, group);
            });

            return [...bySpecifier.entries()].map(([specifier, group]) => {
                const clause = [
                    group.default,
                    group.named.length > 0 ? `{ ${group.named.join(', ')} }` : undefined,
                ]
                    .filter(Boolean)
                    .join(', ');

                return `import ${clause} from '${specifier}';`;
            });
        }

        /**
         * Every import the file needs, as one insertion, plus merges into imports it already has.
         *
         * One insertion rather than one per rewrite: several insertions at the same offset overlap, and
         * ESLint keeps only the first of an overlapping set.
         */
        function importFixes(fixer) {
            const [first] = source.ast.body;
            const fresh = [];
            const edits = [];

            plannedImports().forEach((one) => {
                const existing = imports.get(one.specifier);

                if (!existing) {
                    fresh.push(one);

                    return;
                }

                if (existing.specifiers.some((specifier) => specifier.local.name === one.local)) {
                    return;
                }

                const last = existing.specifiers[existing.specifiers.length - 1];

                edits.push(
                    one.imported === undefined
                        ? fixer.insertTextBefore(last, `${one.local}, `)
                        : fixer.insertTextAfter(
                              last,
                              `, ${one.imported === one.local ? one.local : `${one.imported} as ${one.local}`}`,
                          ),
                );
            });

            if (fresh.length > 0) {
                edits.unshift(fixer.insertTextBefore(first, `${importStatements(fresh).join('\n')}\n`));
            }

            return edits;
        }

        /**
         * Removes the bindings the rewrites leave with no readers.
         *
         * One edit per declaration, not per alias: `const { Mixin, Store, Service } = Shopware;` can lose
         * two of three, and two ranges cut out of the same pattern overlap, which ESLint rejects outright.
         * Rewriting the pattern from its survivors is one edit whatever dies.
         */
        function aliasRemovalFixes(fixer) {
            const text = source.getText();
            const byDeclarator = new Map();

            dead.forEach((name) => {
                const { declarator, property } = aliases.get(name);
                const group = byDeclarator.get(declarator) ?? [];

                group.push(property);
                byDeclarator.set(declarator, group);
            });

            return [...byDeclarator.entries()].map(([declarator, removed]) => {
                const survivors = declarator.id.properties.filter((property) => !removed.includes(property));

                if (survivors.length > 0) {
                    return fixer.replaceText(
                        declarator.id,
                        `{ ${survivors.map((property) => source.getText(property)).join(', ')} }`,
                    );
                }

                // The statement only: a range reaching into the next line touches the fix that line
                // carries, and ESLint keeps just one of a touching pair. Prettier clears the blank line.
                return fixer.remove(declarator.parent);
            });
        }

        /** The replacement for one planned rewrite. */
        function replacementFix(fixer, entry) {
            return entry.kind === 'destructuring'
                ? [fixer.remove(entry.target)]
                : [fixer.replaceText(entry.target, entry.replacement)];
        }

        return {
            // On exit, not enter: ESLint assigns `node.parent` as it traverses, and the planner reads it
            // to find the call around a lookup and the statement around a destructuring.
            'Program:exit'(program) {
                plan(program);

                let carrier = true;

                plans.forEach((entry) => {
                    const isCarrier = carrier;

                    carrier = false;

                    context.report({
                        node: entry.target,
                        messageId: 'preferModule',
                        data: { specifier: entry.specifier, expression: source.getText(entry.node) },
                        fix(fixer) {
                            const edits = replacementFix(fixer, entry);

                            // The first problem carries what the whole file needs; every other one carries
                            // only its own replacement, so none of them overlap.
                            return isCarrier ? [...importFixes(fixer), ...aliasRemovalFixes(fixer), ...edits] : edits;
                        },
                    });
                });
            },
        };
    },
};
