/**
 * @sw-package discovery
 */

const fs = require('fs');
const path = require('path');

const STOREFRONT_ROOT = path.resolve(__dirname, '..', '..');

/**
 * `global.default` always lives in the core Storefront — the rule's home. Candidate snippets and the
 * allow list are read from the directory ESLint runs in (`context.cwd`, optionally narrowed by the
 * `snippetRoot` option), so the rule also covers storefront snippets shipped by other bundles when
 * they are linted from their own directory — without the core Storefront referencing those bundles.
 */
const GLOBAL_DEFAULT_DIR = path.resolve(STOREFRONT_ROOT, '..', '..', 'snippet');

const SKIP_DIRS = new Set(['node_modules', 'vendor', '.git', 'var', 'dist', '.vite', '.tmp', 'custom']);

let duplicateCache = null;

/**
 * Reads the allow list of snippet keys that intentionally keep a global.default duplicate.
 * Set SNIPPET_ALLOW_LIST_DISABLED to bypass it and surface every duplicate, including allowed ones.
 */
function loadAllowList(cwd) {
    if (process.env.SNIPPET_ALLOW_LIST_DISABLED) {
        return [];
    }

    try {
        const list = require(path.join(cwd, 'snippet-global-default-allow-list.js'));
        return Array.isArray(list) ? list : [];
    } catch {
        return [];
    }
}

/**
 * An allow-list entry matches a snippet key either exactly or as a namespace prefix:
 * `account` covers `account.profile.successLabel` and everything else below it.
 */
function isAllowed(key, allowList) {
    return allowList.some(entry => key === entry || key.startsWith(`${entry}.`));
}

/**
 * Flattens a nested snippet object into dotted keys, keeping string leaves only.
 */
function flatten(obj, prefix, out) {
    for (const [key, value] of Object.entries(obj)) {
        const dotted = prefix ? `${prefix}.${key}` : key;
        if (value && typeof value === 'object' && !Array.isArray(value)) {
            flatten(value, dotted, out);
        } else if (typeof value === 'string') {
            out[dotted] = value;
        }
    }
    return out;
}

function readJson(absPath) {
    try {
        return JSON.parse(fs.readFileSync(absPath, 'utf8'));
    } catch {
        return null;
    }
}

/**
 * Collects all `*.json` files living in a `snippet` directory below `dir`, skipping build/vendor dirs.
 */
function collectSnippetFiles(dir, acc) {
    let entries = [];
    try {
        entries = fs.readdirSync(dir, { withFileTypes: true });
    } catch {
        return acc;
    }

    for (const entry of entries) {
        if (entry.isDirectory()) {
            if (!SKIP_DIRS.has(entry.name)) {
                collectSnippetFiles(path.join(dir, entry.name), acc);
            }
        } else if (entry.name.endsWith('.json') && path.basename(dir) === 'snippet') {
            acc.push(path.join(dir, entry.name));
        }
    }
    return acc;
}

/**
 * Builds — and caches — the map of duplicate snippet keys: keyed by the dotted snippet key and
 * holding the matching `global.default` key. A key only counts as a duplicate when its value equals
 * the `global.default` value in every locale.
 */
function getDuplicates(cwd, snippetRoot) {
    if (duplicateCache) {
        return duplicateCache;
    }

    const map = new Map();

    // global.default lives in the core Storefront (the rule's home).
    const globalDefaultByLocale = Object.create(null);
    const locales = [];
    for (const file of fs.existsSync(GLOBAL_DEFAULT_DIR) ? fs.readdirSync(GLOBAL_DEFAULT_DIR) : []) {
        const match = /^storefront\.([a-zA-Z-]+)\.json$/.exec(file);
        if (!match) {
            continue;
        }
        const json = readJson(path.join(GLOBAL_DEFAULT_DIR, file));
        if (!json?.global?.default || typeof json.global.default !== 'object') {
            continue;
        }
        globalDefaultByLocale[match[1]] = json.global.default;
        locales.push(match[1]);
    }

    if (!locales.length) {
        duplicateCache = { map, baseLocale: null };
        return duplicateCache;
    }

    const baseLocale = locales.includes('en') ? 'en' : [...locales].sort()[0];
    const defaultKeys = Object.keys(globalDefaultByLocale[baseLocale]);
    const allowList = loadAllowList(cwd);

    // Candidate storefront snippets, grouped by file name (`<name>.<locale>.json` → one group), flattened per locale.
    const byGroup = new Map();
    for (const file of collectSnippetFiles(path.resolve(cwd, snippetRoot), [])) {
        const match = /^(.+)\.([a-zA-Z-]+)\.json$/.exec(path.basename(file));
        if (!match || !globalDefaultByLocale[match[2]]) {
            continue;
        }
        const locale = match[2];
        const groupKey = path.join(path.dirname(file), match[1]);
        const byLocale = byGroup.get(groupKey) ?? Object.create(null);
        byLocale[locale] = flatten(readJson(file) ?? {}, '', Object.create(null));
        byGroup.set(groupKey, byLocale);
    }

    for (const perLocale of byGroup.values()) {
        const base = perLocale[baseLocale];
        if (!base) {
            continue;
        }

        for (const [key, value] of Object.entries(base)) {
            if (key.startsWith('global.default.') || map.has(key) || isAllowed(key, allowList)) {
                continue;
            }

            const candidate = defaultKeys.find(defaultKey => globalDefaultByLocale[baseLocale][defaultKey] === value
                && locales.every(locale => perLocale[locale] && perLocale[locale][key] === globalDefaultByLocale[locale][defaultKey]));

            if (candidate) {
                map.set(key, candidate);
            }
        }
    }

    duplicateCache = { map, baseLocale };
    return duplicateCache;
}

module.exports = {
    meta: {
        type: 'suggestion',
        docs: {
            description: 'Disallow snippet values that are identical to a `global.default` translation in every locale; '
                + 'reference the `global.default` snippet instead of redefining it. Also disallow defining '
                + '`global.default` keys outside the core Storefront snippets.',
            recommended: true,
        },
        schema: [
            {
                type: 'object',
                properties: {
                    snippetRoot: { type: 'string' },
                },
                additionalProperties: false,
            },
        ],
        messages: {
            useGlobalDefault: 'Snippet Key `{{key}}` duplicates `global.default.{{defaultKey}}`. '
                + 'Please remove it and use `global.default.{{defaultKey}}` instead ({{file}}).',
            noGlobalDefaultDefinition: 'Snippet Key `{{key}}` is defined in the reserved `global.default` namespace. '
                + 'Only the core Storefront snippets (`Resources/snippet/storefront.*.json`) may define '
                + '`global.default` keys. Either reference an existing `global.default` snippet directly '
                + 'or define the snippet in your own namespace ({{file}}).',
        },
    },

    create(context) {
        const cwd = context.cwd ?? process.cwd();
        const snippetRoot = context.options[0]?.snippetRoot ?? '.';
        const filename = context.filename ?? context.getFilename();
        const isCentralFile = path.dirname(path.resolve(filename)) === GLOBAL_DEFAULT_DIR;
        const allowList = loadAllowList(cwd);

        const { map, baseLocale } = getDuplicates(cwd, snippetRoot);

        // Report each duplicate once, on the base-locale file — the key exists in every locale (all-locale gate), so per-locale reporting would only duplicate each finding.
        const localeMatch = /\.([a-zA-Z-]+)\.json$/.exec(path.basename(filename));
        const reportDuplicates = map.size > 0 && localeMatch !== null && localeMatch[1] === baseLocale;

        if (isCentralFile && !reportDuplicates) {
            return {};
        }

        const relativeFile = path.relative(cwd, filename);
        const keyPath = [];

        return {
            Member(node) {
                keyPath.push(node.name.value);

                if (node.value.type !== 'String') {
                    return;
                }

                const dottedKey = keyPath.join('.');

                if (dottedKey.startsWith('global.default.')) {
                    if (!isCentralFile && !isAllowed(dottedKey, allowList)) {
                        context.report({
                            node,
                            messageId: 'noGlobalDefaultDefinition',
                            data: {
                                key: dottedKey,
                                file: relativeFile,
                            },
                        });
                    }
                    return;
                }

                if (!reportDuplicates) {
                    return;
                }

                const defaultKey = map.get(dottedKey);
                if (!defaultKey) {
                    return;
                }

                context.report({
                    node,
                    messageId: 'useGlobalDefault',
                    data: {
                        key: dottedKey,
                        defaultKey,
                        file: relativeFile,
                    },
                });
            },
            'Member:exit'() {
                keyPath.pop();
            },
        };
    },
};
