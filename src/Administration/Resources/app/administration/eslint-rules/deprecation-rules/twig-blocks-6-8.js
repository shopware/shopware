/**
 * @sw-package framework
 *
 * Codemods for the Administration Twig blocks that Shopware 6.8 renames or removes, listed per
 * component in data/twig-blocks-6-8.json. They look at block overrides in `.html.twig` templates
 * (`{% block name %}`) and at `<sw-block extends="name">` in `.vue` files.
 *
 * - `renamed`: renames the overridden block. It writes a TODO comment instead when the template
 *   already overrides the new block, or when the override reads the `active` slot variable of the
 *   legacy `sw-tabs` content slot, which the new `mt-tabs` block does not have.
 * - `removed`: writes a TODO comment with the block to move the override to.
 *
 * A block name that also exists in other components is only handled in a template that belongs
 * to the component, i.e. in a folder or file named after it, like `sw-product-detail/sw-product-detail.html.twig`.
 */

const blocks = require('./data/twig-blocks-6-8.json');
const { createTodoReporter, todoMessages } = require('./utils/todo-comment');
const { componentContext } = require('./utils/component-context');

const BLOCK_TAG = /\{%-?\s*(block|endblock)\b\s*([\w.-]*)\s*-?%\}/g;

/**
 * @typedef {object} TwigBlock
 * @property {string} name
 * @property {number} start offset of `{% block`
 * @property {[number, number]} nameRange
 * @property {[number, number]} body between the block and the endblock tag
 * @property {[number, number] | null} endNameRange the name of a named `{% endblock name %}`
 */

/**
 * The `{% block %}` tags of a template with their bodies.
 *
 * @param {string} text
 * @returns {TwigBlock[]}
 */
function parseTwigBlocks(text) {
    const found = [];
    const open = [];

    for (const match of text.matchAll(BLOCK_TAG)) {
        const [tag, keyword, name] = match;
        const nameStart = name ? match.index + tag.indexOf(name, keyword.length + 2) : -1;
        const nameRange = name ? [nameStart, nameStart + name.length] : null;

        if (keyword === 'block' && name) {
            open.push({ name, start: match.index, nameRange, bodyStart: match.index + tag.length });
            continue;
        }

        const block = open.pop();

        if (keyword === 'endblock' && block) {
            found.push({
                name: block.name,
                start: block.start,
                nameRange: block.nameRange,
                body: [block.bodyStart, match.index],
                endNameRange: name === block.name ? nameRange : null,
            });
        }
    }

    // Unclosed blocks still count as overrides.
    open.forEach((block) => {
        found.push({ ...block, body: [block.bodyStart, text.length], endNameRange: null });
    });

    return found.sort((left, right) => left.start - right.start);
}

/**
 * name → [{ component, value }]
 *
 * @param {Record<string, Record<string, string | null>>} byComponent
 */
function indexByName(byComponent) {
    const index = new Map();

    Object.entries(byComponent).forEach(([component, entries]) => {
        Object.entries(entries).forEach(([name, value]) => {
            index.set(name, [...(index.get(name) ?? []), { component, value }]);
        });
    });

    return index;
}

const RENAMED = indexByName(blocks.renamed);
const REMOVED = indexByName(blocks.removed);
const SHARED = new Set(blocks.shared);
const ACTIVE_SLOT = new Set(blocks.activeSlot);

/**
 * The components a template belongs to, including the ones whose template it inherits.
 *
 * @param {import('eslint').Rule.RuleContext} context
 */
function templateComponents(context) {
    const names = componentContext(context, null);
    const parents = Object.entries(blocks.extendedBy)
        .filter(([, children]) => children.some((child) => names.includes(child)))
        .map(([parent]) => parent);

    return [...names, ...parents];
}

/**
 * The entry for a block override, or null when the name is ambiguous for this template.
 *
 * @param {{ component: string, value: string | null }[] | undefined} entries
 * @param {string} name
 * @param {string[]} components
 */
function resolve(entries, name, components) {
    if (!entries) {
        return null;
    }

    const own = entries.find((entry) => components.includes(entry.component));

    if (own) {
        return own;
    }

    return SHARED.has(name) || entries.length > 1 ? null : entries[0];
}

/**
 * The `<sw-block extends="name">` elements of a `.vue` template with the range of their name.
 *
 * @param {import('vue-eslint-parser').AST.VElement} node
 */
function swBlockExtends(node) {
    if (node.name !== 'sw-block') {
        return null;
    }

    const attribute = node.startTag.attributes.find((candidate) => !candidate.directive && candidate.key.name === 'extends');

    if (!attribute?.value) {
        return null;
    }

    const { value, range } = attribute.value;
    const quoted = range[1] - range[0] > value.length;

    return { name: value, nameRange: quoted ? [range[0] + 1, range[1] - 1] : range };
}

/**
 * Calls `onBlock` once per overridden block: for every Twig block tag and every `<sw-block extends>`.
 *
 * @param {import('eslint').Rule.RuleContext} context
 * @param {(block: { name: string, offset: number, nameRanges: [number, number][], body: string, element?: object }) => void} onBlock
 *   `body` is the Twig block content; for an `<sw-block>`, `element` is the element.
 * @param {(names: Set<string>) => void} [onNames] receives every block name the template overrides first
 */
function visitBlocks(context, onBlock, onNames) {
    const parserServices = context.sourceCode.parserServices;

    if (!parserServices?.defineTemplateBodyVisitor) {
        return {};
    }

    const text = context.sourceCode.text;
    let scanned = false;

    return parserServices.defineTemplateBodyVisitor({
        VElement() {
            if (!scanned) {
                scanned = true;

                const twigBlocks = parseTwigBlocks(text);
                const vueBlocks = [];

                collectSwBlocks(context.sourceCode.ast.templateBody, vueBlocks);
                onNames?.(new Set([...twigBlocks.map((block) => block.name), ...vueBlocks.map((block) => block.name)]));

                twigBlocks.forEach((block) => {
                    onBlock({
                        name: block.name,
                        offset: block.start,
                        nameRanges: [block.nameRange, block.endNameRange].filter(Boolean),
                        body: text.slice(...block.body),
                    });
                });
                vueBlocks.forEach((block) => onBlock(block));
            }
        },
    }, {});
}

/**
 * @param {import('vue-eslint-parser').AST.VElement} element
 * @param {object[]} found
 */
function collectSwBlocks(element, found) {
    const extended = swBlockExtends(element);

    if (extended) {
        found.push({
            name: extended.name,
            offset: element.range[0],
            nameRanges: [extended.nameRange],
            body: '',
            element,
        });
    }

    element.children
        .filter((child) => child.type === 'VElement')
        .forEach((child) => collectSwBlocks(child, found));
}

/** @type {import('eslint').Rule.RuleModule} */
const renamed = {
    meta: {
        type: 'problem',
        docs: {
            description: 'Rename overrides of Administration Twig blocks that Shopware 6.8 renames',
            category: 'Shopware 6.8 Migration',
            recommended: false,
        },
        fixable: 'code',
        schema: [],
        messages: {
            ...todoMessages,
            rename: 'Block "{{ from }}" of "{{ component }}" is renamed to "{{ to }}" in 6.8.',
        },
    },
    create(context) {
        const sourceCode = context.sourceCode;
        const reporter = createTodoReporter(context);
        const components = templateComponents(context);
        let overridden = new Set();

        const report = (block, hint) => {
            if (block.element) {
                reporter.reportTemplate(block.element, hint);
            } else {
                reporter.reportTwig(block.offset, hint);
            }
        };

        return visitBlocks(context, (block) => {
            const entry = resolve(RENAMED.get(block.name), block.name, components);

            if (!entry) {
                return;
            }

            const prefix = `Block ${block.name} of ${entry.component} is renamed to ${entry.value} in 6.8.`;
            const body = block.element ? sourceCode.getText(block.element) : block.body;

            if (overridden.has(entry.value)) {
                report(block, `${prefix} This template already overrides ${entry.value}, merge this override into it.`);

                return;
            }

            if (ACTIVE_SLOT.has(block.name) && /(?<![\w$.])active(?![\w$])/.test(body)) {
                report(block, `${prefix} Rename this override and use activeTab instead of the active slot variable of sw-tabs.`);

                return;
            }

            context.report({
                loc: sourceCode.getLocFromIndex(block.offset),
                messageId: 'rename',
                data: { from: block.name, to: entry.value, component: entry.component },
                fix: (fixer) => block.nameRanges.map((range) => fixer.replaceTextRange(range, entry.value)),
            });
        }, (names) => {
            overridden = names;
        });
    },
};

/** @type {import('eslint').Rule.RuleModule} */
const removed = {
    meta: {
        type: 'problem',
        docs: {
            description: 'Comment overrides of Administration Twig blocks that Shopware 6.8 removes',
            category: 'Shopware 6.8 Migration',
            recommended: false,
        },
        fixable: 'code',
        schema: [],
        messages: todoMessages,
    },
    create(context) {
        const reporter = createTodoReporter(context);
        const components = templateComponents(context);

        return visitBlocks(context, (block) => {
            const entry = resolve(REMOVED.get(block.name), block.name, components);

            if (!entry) {
                return;
            }

            const hint = entry.value
                ? `Block ${block.name} of ${entry.component} is removed in 6.8. Move this override to ${entry.value}.`
                : `Block ${block.name} of ${entry.component} is removed in 6.8 without replacement. Remove this override.`;

            if (block.element) {
                reporter.reportTemplate(block.element, hint);
            } else {
                reporter.reportTwig(block.offset, hint);
            }
        });
    },
};

module.exports = {
    renamed,
    removed,
    parseTwigBlocks,
};
