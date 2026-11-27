/**
 * @sw-package framework
 *
 * What the detail view of layout "Split" shows beside the texts of the break check: the changed code, what a
 * plugin runs into and the fix as code. The break check does not report these yet, so the two breaks of pull
 * request #20959 below are written by hand. The changed code is the real diff of that pull request. The plugin
 * and the fix are a proposal for what the break check could generate per rule.
 */

/** @private */
export interface DiffLine {
    /** ` ` unchanged, `-` removed, `+` added, `!` the line a note is about. */
    type: ' ' | '-' | '+' | '!';
    /** The line numbers in the base and in the head, where the line exists. */
    before?: number;
    after?: number;
    text: string;
    /** Said below the line. */
    note?: string;
}

/** @private */
export interface CodeBlock {
    file: string;
    lines: DiffLine[];
}

/** @private */
export interface DetailExample {
    /** What changed, as a title and in one plain sentence. */
    headline: string;
    summary: string;
    changed: CodeBlock;
    /** What changes for a plugin, in one sentence, and an override that runs into it. */
    pluginEffect: string;
    plugin: CodeBlock;
    /** The steps of the fix, and the code after it. */
    steps: string[];
    fix: CodeBlock;
}

const SCOPE_VARIABLE: DetailExample = {
    headline: 'itemIndex is no longer available in 5 blocks',
    summary: 'The slot no longer passes itemIndex, so the 5 blocks inside this template lose it.',
    changed: {
        file: 'sw-order-line-items-grid.html.twig',
        lines: [
            { type: ' ', before: 323, after: 301, text: '{% block sw_order_line_items_grid_grid_actions %}' },
            { type: '-', before: 324, text: '<template #actions="{ item, itemIndex }">' },
            { type: '+', after: 302, text: '<template #actions="{ item }">' },
            { type: ' ', before: 325, after: 303, text: '    {% block sw_order_line_items_grid_grid_actions_show %}' },
        ],
    },
    pluginEffect: 'A plugin that overrides one of the blocks and uses itemIndex now fails at render time.',
    plugin: {
        file: 'A plugin override',
        lines: [
            { type: ' ', text: '{% block sw_order_line_items_grid_grid_actions_delete %}' },
            {
                type: '!',
                text: '    <sw-context-menu-item @click="removeLine(itemIndex)">',
                note: 'itemIndex is undefined here after this change.',
            },
            { type: ' ', text: '        Remove' },
            { type: ' ', text: '    </sw-context-menu-item>' },
            { type: ' ', text: '{% endblock %}' },
        ],
    },
    steps: [
        'Keep passing itemIndex to the slot while v6.8.0.0 is off.',
        'Put the marker above each of the 5 blocks, so the removal is announced for v6.8.0.',
    ],
    fix: {
        file: 'sw-order-line-items-grid.html.twig',
        lines: [
            { type: ' ', before: 301, after: 301, text: '{% block sw_order_line_items_grid_grid_actions %}' },
            { type: '-', before: 302, text: '<template #actions="{ item }">' },
            { type: '+', after: 302, text: '<template #actions="{ item, itemIndex }">' },
            {
                type: '+',
                after: 303,
                text: '    {# @bc-change ScopeVariableRemoval version:v6.8.0 variable:itemIndex #}',
            },
            { type: ' ', before: 303, after: 304, text: '    {% block sw_order_line_items_grid_grid_actions_show %}' },
        ],
    },
};

const PARAMETER: DetailExample = {
    headline: 'onDeleteItem no longer takes itemIndex',
    summary: 'The second parameter is gone. The method now looks the index up itself.',
    changed: {
        file: 'sw-order-line-items-grid/index.js',
        lines: [
            { type: '-', before: 321, text: 'onDeleteItem(item, itemIndex) {' },
            { type: '+', after: 330, text: 'onDeleteItem(item) {' },
            { type: ' ', before: 322, after: 331, text: '    if (item.isNew()) {' },
            {
                type: '+',
                after: 332,
                text: '        const itemIndex = this.order.lineItems.findIndex((lineItem) => lineItem?.id === item.id);',
            },
        ],
    },
    pluginEffect: 'A plugin that overrides onDeleteItem and reads its second parameter now gets undefined.',
    plugin: {
        file: 'A plugin override',
        lines: [
            { type: ' ', text: "Shopware.Component.override('sw-order-line-items-grid', {" },
            { type: ' ', text: '    methods: {' },
            { type: ' ', text: '        onDeleteItem(item, itemIndex) {' },
            {
                type: '!',
                text: '            this.removedIndexes.push(itemIndex);',
                note: 'itemIndex is undefined here after this change.',
            },
            { type: ' ', text: "            return this.$super('onDeleteItem', item, itemIndex);" },
            { type: ' ', text: '        },' },
            { type: ' ', text: '    },' },
            { type: ' ', text: '});' },
        ],
    },
    steps: [
        'Keep the parameter and keep passing it from the template.',
        'Mark the method with the marker, and remove the parameter in the major.',
    ],
    fix: {
        file: 'sw-order-line-items-grid/index.js',
        lines: [
            { type: '+', after: 330, text: '/**' },
            { type: '+', after: 331, text: ' * @bc-change ParameterRemoval version:v6.8.0 parameter:itemIndex' },
            { type: '+', after: 332, text: ' */' },
            { type: '-', before: 330, text: 'onDeleteItem(item) {' },
            { type: '+', after: 333, text: 'onDeleteItem(item, itemIndex) {' },
            { type: ' ', before: 331, after: 334, text: '    if (item.isNew()) {' },
        ],
    },
};

const EXAMPLES: Record<string, DetailExample> = {
    'component:sw-order-line-items-grid|block_scope_missing': SCOPE_VARIABLE,
    'component:sw-order-line-items-grid|param_missing': PARAMETER,
};

/**
 * @private
 *
 * The example for the breaks of `rule` in the unit, where one is written.
 */
export function detailExampleOf(unit: string, rule: string): DetailExample | undefined {
    return EXAMPLES[`${unit}|${rule}`];
}
