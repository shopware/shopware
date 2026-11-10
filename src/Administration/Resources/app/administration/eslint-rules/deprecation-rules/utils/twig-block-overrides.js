/**
 * @sw-package framework
 *
 * Shared logic of the Twig block codemods `no-renamed-twig-blocks` and `no-removed-twig-blocks`: finds the block
 * overrides of an `.html.twig` file that a data entry covers, and words what to use instead.
 */

const fs = require('fs');
const path = require('path');
const { findBlocks } = require('../upgrade-helpers/twig-blocks');
const { twigTargets } = require('../upgrade-helpers/twig-target');
const { isTwigFile } = require('../upgrade-helpers/todo-comment');

const SHARED_NAME_MESSAGE = 'Block name is used by several core components. Check which one you override.';

const COMMENT = /\{#[\s\S]*?#\}/g;
const TAG = /\{%-?\s*(?:block\s+[A-Za-z0-9_]+|endblock(?:\s+([A-Za-z0-9_]+))?)\s*-?%\}/g;

/**
 * @typedef {object} BlockEntry
 * @property {string} name the block name of the core component
 * @property {string} component the core component
 * @property {string[]} components the core component and the core components that inherit the block from it
 * @property {boolean} shared whether other core components use the name as well
 */

/**
 * @param {BlockEntry[]} entries
 * @returns {Map<string, BlockEntry[]>}
 */
function indexByName(entries) {
    const index = new Map();

    for (const entry of entries) {
        index.set(entry.name, [...(index.get(entry.name) ?? []), entry]);
    }

    return index;
}

function readScripts(directory) {
    return ['index.js', 'index.ts'].map((name) => {
        try {
            return fs.readFileSync(path.join(directory, name), 'utf8');
        } catch {
            return '';
        }
    }).join('\n');
}

/**
 * Whether the template belongs to a component the extension registers itself: the script next to it registers a
 * component and overrides or extends none, or the script one folder up registers the template's component lazily,
 * as in `Component.register('sw-cms-el-config-acme', () => import('./config'))`. Such a template defines its own
 * blocks, even when it copies the names of core blocks, so nothing is removed or renamed there.
 *
 * @param {import('eslint').Rule.RuleContext} context
 * @returns {boolean}
 */
function registersOwnComponent(context) {
    const filename = context.physicalFilename ?? context.filename ?? '';
    const directory = path.dirname(filename);
    const sibling = readScripts(directory);

    if (/Component\s*\.\s*(override|extend)\(/.test(sibling)) {
        return false;
    }

    if (/Component\s*\.\s*register\(/.test(sibling)) {
        return true;
    }

    const names = [path.basename(filename).replace(/(\.html)?\.twig$/, ''), path.basename(directory)];
    const registered = [...readScripts(path.dirname(directory)).matchAll(/Component\s*\.\s*register\(\s*(['"`])([\w-]+)\1/g)];

    return registered.some((match) => names.includes(match[2]));
}

function commentRanges(text) {
    return [...text.matchAll(COMMENT)].map((match) => [match.index, match.index + match[0].length]);
}

function isInside(ranges, index) {
    return ranges.some(([start, end]) => index >= start && index < end);
}

/**
 * The entry that applies to an override of `entries[0].name`: the one for an override target of the template,
 * any entry of a name only one core component uses, `'unknown'` when several do and the target is unknown,
 * otherwise `null`.
 *
 * @param {BlockEntry[]} entries
 * @param {string[]} targets
 * @returns {BlockEntry|'unknown'|null}
 */
function entryFor(entries, targets) {
    const matching = entries.find((entry) => entry.components.some((component) => targets.includes(component)));

    if (matching) {
        return matching;
    }

    if (entries.length === 1 && !entries[0].shared) {
        return entries[0];
    }

    return targets.length === 0 ? 'unknown' : null;
}

/**
 * The name range of the `{% endblock name %}` that closes the block starting at `from`, if it repeats the name.
 *
 * @param {string} text
 * @param {number} from the end of the `{% block %}` tag
 * @param {Array<[number, number]>} comments
 * @returns {[number, number]|null}
 */
function namedEndBlockRange(text, from, comments) {
    let depth = 0;
    const tag = new RegExp(TAG.source, 'g');

    tag.lastIndex = from;

    for (let match = tag.exec(text); match; match = tag.exec(text)) {
        if (isInside(comments, match.index)) {
            continue;
        }

        if (!/^\{%-?\s*endblock/.test(match[0])) {
            depth += 1;
            continue;
        }

        if (depth > 0) {
            depth -= 1;
            continue;
        }

        if (!match[1]) {
            return null;
        }

        const start = match.index + match[0].lastIndexOf(match[1]);

        return [start, start + match[1].length];
    }

    return null;
}

/**
 * Calls `onOverride` for every block of the linted `.html.twig` file that an entry covers. Blocks in Twig comments
 * and templates of components the extension registers itself are skipped. A name that several core components use
 * is only matched when the override target is known; otherwise the block is reported without a fix.
 *
 * @param {import('eslint').Rule.RuleContext} context
 * @param {Map<string, BlockEntry[]>} entriesByName
 * @param {(block: { name: string, range: [number, number], nameRange: [number, number] }, entry: BlockEntry, file: { text: string, comments: Array<[number, number]>, names: Set<string> }) => void} onOverride
 * @param {{ reportSharedName?: (name: string) => boolean }} [options]
 */
function forEachBlockOverride(context, entriesByName, onOverride, { reportSharedName = () => true } = {}) {
    if (!isTwigFile(context)) {
        return;
    }

    const text = context.sourceCode.text;
    const comments = commentRanges(text);
    const blocks = findBlocks(text).filter((block) => !isInside(comments, block.range[0]));
    const file = { text, comments, names: new Set(blocks.map((block) => block.name)) };
    let targets = null;

    for (const block of blocks) {
        const entries = entriesByName.get(block.name);

        if (!entries) {
            continue;
        }

        if (targets === null && registersOwnComponent(context)) {
            return;
        }

        targets ??= twigTargets(context);
        const entry = entryFor(entries, targets);

        if (entry === 'unknown') {
            if (reportSharedName(block.name)) {
                context.report({
                    loc: {
                        start: context.sourceCode.getLocFromIndex(block.range[0]),
                        end: context.sourceCode.getLocFromIndex(block.range[1]),
                    },
                    message: SHARED_NAME_MESSAGE,
                });
            }

            continue;
        }

        if (entry) {
            onOverride(block, entry, file);
        }
    }
}

/**
 * "Block <name> is removed …" with what to use instead.
 *
 * @param {object} options
 * @param {string} options.name
 * @param {string} options.component
 * @param {boolean} options.shared names the component when other core components use the name as well
 * @param {string} [options.why] appended to "is removed", e.g. " with the legacy sw-tabs branch"
 * @param {string|null|{ use?: string, in?: string, extend?: string, tab?: string, hint?: string }} options.replacement
 * @param {string} [options.flag] the 6.7 version from which the replacement renders with the `v6.8.0.0` flag
 * @returns {string}
 */
function removalMessage({ name, component, shared, why = '', replacement, flag }) {
    const subject = `Block ${name}${shared ? ` of ${component}` : ''} is removed${why}`;
    const note = flag ? ` (renders with the v6.8.0.0 flag from ${flag})` : '';
    const target = typeof replacement === 'string' ? { use: replacement } : replacement;

    if (!target) {
        return `${subject} without replacement.`;
    }

    if (target.extend) {
        return `${subject} without replacement. Extend ${target.extend} instead.`;
    }

    if (target.tab) {
        return `${subject}. Add the tab to the ${target.tab} computed instead${note}.`;
    }

    if (target.hint) {
        return `${subject}. ${target.hint}`;
    }

    return `${subject}. Override ${target.use}${target.in ? ` in ${target.in}` : ''} instead${note}.`;
}

module.exports = {
    SHARED_NAME_MESSAGE,
    indexByName,
    forEachBlockOverride,
    namedEndBlockRange,
    removalMessage,
};
