/**
 * @sw-package discovery
 */

const fs = require('fs');
const path = require('path');

const ADMIN_ROOT = path.resolve(__dirname, '..', '..');

/**
 * `global.default` always lives in this Administration — the rule's home. Candidate snippets and the
 * allow list are instead read from the directory ESLint runs in (`context.cwd`), so the rule also
 * covers admin modules shipped by other bundles when they are linted from their own directory —
 * without this Administration ever referencing those bundles.
 */
const GLOBAL_DEFAULT_DIR = path.join(ADMIN_ROOT, 'src', 'app', 'snippet');

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
function getDuplicates(cwd) {
    if (duplicateCache) {
        return duplicateCache;
    }

    const map = new Map();

    const central = GLOBAL_DEFAULT_DIR;
    const globalDefaultByLocale = Object.create(null);
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
        duplicateCache = { map, baseLocale: null };
        return duplicateCache;
    }

    const baseLocale = locales.includes('en') ? 'en' : [...locales].sort()[0];
    const defaultKeys = Object.keys(globalDefaultByLocale[baseLocale]);
    const allowList = loadAllowList(cwd);

    // Group snippet files per module directory, flattened by locale; the Map + prototype-less objects keep snippet keys from polluting Object.prototype.
    const byDir = new Map();
    for (const file of collectSnippetFiles(path.join(cwd, 'src'), [])) {
        const locale = path.basename(file, '.json');
        if (!globalDefaultByLocale[locale]) {
            continue;
        }

        const dir = path.dirname(file);
        const byLocale = byDir.get(dir) ?? Object.create(null);
        byLocale[locale] = flatten(readJson(file) ?? {}, '', Object.create(null));
        byDir.set(dir, byLocale);
    }

    for (const perLocale of byDir.values()) {
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
                + '`global.default` keys outside the central Administration snippets.',
            recommended: true,
        },
        schema: [],
        messages: {
            useGlobalDefault: 'Snippet Key `{{key}}` duplicates `global.default.{{defaultKey}}`. '
                + 'Please remove it and use `global.default.{{defaultKey}}` instead ({{file}}).',
            noGlobalDefaultDefinition: 'Snippet Key `{{key}}` is defined in the reserved `global.default` namespace. '
                + 'Only the central Administration snippets (`src/app/snippet`) may define `global.default` keys. '
                + 'Either reference an existing `global.default` snippet directly or define the snippet '
                + 'in your own namespace ({{file}}).',
        },
    },

    create(context) {
        const cwd = context.cwd ?? process.cwd();
        const filename = context.filename ?? context.getFilename();
        const isCentralFile = path.dirname(path.resolve(filename)) === GLOBAL_DEFAULT_DIR;
        const allowList = loadAllowList(cwd);

        const { map, baseLocale } = getDuplicates(cwd);

        // Report each duplicate once, on the base-locale file — the key exists in every locale (all-locale gate), so per-locale reporting would only duplicate each finding.
        const reportDuplicates = map.size > 0 && path.basename(filename, '.json') === baseLocale;

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
