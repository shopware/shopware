/**
 * @sw-package discovery
 */

const fs = require('fs');
const path = require('path');

const ADMIN_ROOT = path.resolve(__dirname, '..', '..');
const SRC_ROOT = path.join(ADMIN_ROOT, 'src');

/**
 * The Storefront ships admin modules (e.g. sw-theme-manager) that are loaded into this
 * Administration at runtime and therefore share the same `global.default`. Their snippets are
 * scanned alongside the Administration's own so duplicates there are detected too.
 */
const STOREFRONT_ADMIN_SRC = path.resolve(ADMIN_ROOT, '..', '..', '..', '..', 'Storefront', 'Resources', 'app', 'administration', 'src');

const ALLOW_LIST_FILE = path.join(ADMIN_ROOT, 'snippet-global-default-allow-list.js');

let duplicateCache = null;

/**
 * Reads the allow list of snippet keys that intentionally keep a global.default duplicate.
 * Set SNIPPET_ALLOW_LIST_DISABLED to bypass it and surface every duplicate, including allowed ones.
 */
function loadAllowList() {
    if (process.env.SNIPPET_ALLOW_LIST_DISABLED) {
        return [];
    }

    try {
        const list = require(ALLOW_LIST_FILE);
        return Array.isArray(list) ? list : [];
    } catch {
        return [];
    }
}

/**
 * An allow-list entry matches a snippet key either exactly or as a namespace prefix:
 * `sw-privileges` covers `sw-privileges.roles.editor` and everything else below it.
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

function collectSnippetFiles(dir, acc) {
    let entries = [];
    try {
        entries = fs.readdirSync(dir, { withFileTypes: true });
    } catch {
        return acc;
    }

    for (const entry of entries) {
        const full = path.join(dir, entry.name);
        if (entry.isDirectory()) {
            collectSnippetFiles(full, acc);
        } else if (entry.name.endsWith('.json') && path.basename(dir) === 'snippet') {
            acc.push(full);
        }
    }
    return acc;
}

/**
 * Builds — and caches — the map of duplicate snippet keys: keyed by the dotted snippet key
 * (globally unique by module namespace) and holding the matching `global.default` key.
 */
function getDuplicates() {
    if (duplicateCache) {
        return duplicateCache;
    }

    const map = new Map();

    const central = path.join(SRC_ROOT, 'app', 'snippet');
    const globalDefaultByLocale = {};
    const locales = [];
    for (const file of fs.existsSync(central) ? fs.readdirSync(central) : []) {
        if (!file.endsWith('.json')) {
            continue;
        }
        const json = readJson(path.join(central, file));
        if (json?.global?.default && typeof json.global.default === 'object') {
            globalDefaultByLocale[path.basename(file, '.json')] = json.global.default;
            locales.push(path.basename(file, '.json'));
        }
    }

    if (!locales.length) {
        duplicateCache = map;
        return duplicateCache;
    }

    const baseLocale = locales[0];
    const defaultKeys = Object.keys(globalDefaultByLocale[baseLocale]);
    const allowList = loadAllowList();

    // Group snippet files by their directory, flattened per locale.
    const byDir = {};
    const candidateFiles = [SRC_ROOT, STOREFRONT_ADMIN_SRC].flatMap((root) => collectSnippetFiles(root, []));
    for (const file of candidateFiles) {
        const locale = path.basename(file, '.json');
        if (!globalDefaultByLocale[locale]) {
            continue;
        }
        const dir = path.dirname(file);
        (byDir[dir] ??= {})[locale] = flatten(readJson(file) ?? {}, '', {});
    }

    for (const perLocale of Object.values(byDir)) {
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

    duplicateCache = map;
    return duplicateCache;
}

module.exports = {
    meta: {
        type: 'suggestion',
        docs: {
            description: 'Disallow snippet values that are identical to a `global.default` translation in every locale; '
                + 'reference the `global.default` snippet instead of redefining it.',
            recommended: true,
        },
        schema: [],
        messages: {
            useGlobalDefault: 'Snippet Key `{{key}}` duplicates `global.default.{{defaultKey}}`. '
                + 'Please remove it and use `global.default.{{defaultKey}}` instead ({{file}}).',
        },
    },

    create(context) {
        const map = getDuplicates();
        if (!map.size) {
            return {};
        }

        const filename = context.filename ?? context.getFilename();
        const relativeFile = path.relative(ADMIN_ROOT, filename);
        const keyPath = [];

        return {
            Member(node) {
                keyPath.push(node.name.value);

                if (node.value.type !== 'String') {
                    return;
                }

                const dottedKey = keyPath.join('.');
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
