/**
 * Runs `.html.twig` files through the real twig-vue processor, which wraps them in a `<template>`
 * that is not in the file. A spot right inside a Twig block has no element of its own, so its review
 * comment has to go above its own line, and a second run must not add it again.
 */
const { createTempFiles, fixTwig, lintTwig, todoTemplate } = require('./tester');

const STORE_HINT = 'Use the swOrderDetail store.';
const DATE_FILTER_TEMPLATE = 'Add a dateFilter computed.';

function countOf(text, part) {
    return text.split(part).length - 1;
}

describe('createApiUsageRule() through the twig-vue processor', () => {
    let files;
    let orderDetailTemplate;
    let orderListTemplate;

    beforeAll(() => {
        files = createTempFiles({
            'src/extension/sw-order-detail/index.js':
                "import template from './swag-order-detail.html.twig';\nShopware.Component.override('sw-order-detail', { template });\n",
            'src/extension/sw-order-detail/swag-order-detail.html.twig': '',
            'src/extension/sw-order-list/index.js':
                "import template from './sw-order-list.html.twig';\nShopware.Component.override('sw-order-list', { template });\n",
            'src/extension/sw-order-list/sw-order-list.html.twig': '',
        });
        orderDetailTemplate = files.path('src/extension/sw-order-detail/swag-order-detail.html.twig');
        orderListTemplate = files.path('src/extension/sw-order-list/sw-order-list.html.twig');
    });

    afterAll(() => {
        files.remove();
    });

    it('reports a mustache right inside a block on its own line', () => {
        const messages = lintTwig('{% block a %}\n    {{ isLoading }}\n{% endblock %}\n', orderDetailTemplate);

        expect(messages).toEqual([expect.objectContaining({ line: 2, column: 5 })]);
    });

    it('puts the comment of a mustache right inside a block above its line, once', () => {
        const code = '{% block sw_order_detail_actions %}\n    {% parent %}\n    {{ isLoading ? "…" : "" }}\n{% endblock %}\n';
        const expected = `{% block sw_order_detail_actions %}\n    {% parent %}\n    ${todoTemplate(STORE_HINT)}\n    {{ isLoading ? "…" : "" }}\n{% endblock %}\n`;
        const firstRun = fixTwig(code, orderDetailTemplate);

        expect(firstRun).toBe(expected);
        expect(fixTwig(firstRun, orderDetailTemplate)).toBe(expected);
    });

    it('puts the comment of a mustache on the first line of the file above it, once', () => {
        const code = '{{ isLoading }}\n';
        const expected = `${todoTemplate(STORE_HINT)}\n{{ isLoading }}\n`;
        const firstRun = fixTwig(code, orderDetailTemplate);

        expect(firstRun).toBe(expected);
        expect(fixTwig(firstRun, orderDetailTemplate)).toBe(expected);
    });

    it('puts the comment of a block on one line above that line, once', () => {
        const code = '{% block a %}\n{% endblock %}\n{% block b %}{{ isLoading }}{% endblock %}\n';
        const expected = `{% block a %}\n{% endblock %}\n${todoTemplate(STORE_HINT)}\n{% block b %}{{ isLoading }}{% endblock %}\n`;
        const firstRun = fixTwig(code, orderDetailTemplate);

        expect(firstRun).toBe(expected);
        expect(fixTwig(firstRun, orderDetailTemplate)).toBe(expected);
    });

    it('puts the comment of a multi-line mustache above its first line', () => {
        const code = '{% block a %}\n    {{\n        isLoading\n    }}\n{% endblock %}\n';
        const expected = `{% block a %}\n    ${todoTemplate(STORE_HINT)}\n    {{\n        isLoading\n    }}\n{% endblock %}\n`;

        expect(fixTwig(code, orderDetailTemplate)).toBe(expected);
        expect(fixTwig(expected, orderDetailTemplate)).toBe(expected);
    });

    it('puts the comment of a mustache in an element above the element, once', () => {
        const code = '{% block a %}\n    <span>\n        {{ isLoading }}\n    </span>\n{% endblock %}\n';
        const expected = `{% block a %}\n    ${todoTemplate(STORE_HINT)}\n    <span>\n        {{ isLoading }}\n    </span>\n{% endblock %}\n`;
        const firstRun = fixTwig(code, orderDetailTemplate);

        expect(firstRun).toBe(expected);
        expect(fixTwig(firstRun, orderDetailTemplate)).toBe(expected);
    });

    it('keeps an eslint-disable-next-line directive directly above the mustache', () => {
        const code = '{% block a %}\n    <!-- eslint-disable-next-line vue/no-undef-properties -->\n    {{ isLoading }}\n{% endblock %}\n';
        const expected = `{% block a %}\n    ${todoTemplate(STORE_HINT)}\n    <!-- eslint-disable-next-line vue/no-undef-properties -->\n    {{ isLoading }}\n{% endblock %}\n`;
        const firstRun = fixTwig(code, orderDetailTemplate);

        expect(firstRun).toBe(expected);
        expect(fixTwig(firstRun, orderDetailTemplate)).toBe(expected);
    });

    it('fixes and comments every spot of a template in one run and changes nothing in the second', () => {
        const code = [
            '{% block sw_order_list_grid_columns %}',
            '    {% parent %}',
            '    {{ dateFilter(item.orderDateTime) }}',
            '    <sw-label :variant="getDeliveryState(item).technicalName">',
            '        {{ getDeliveryState(item).translated.name }}',
            '    </sw-label>',
            '{% endblock %}',
            '',
        ].join('\n');
        const expected = [
            '{% block sw_order_list_grid_columns %}',
            '    {% parent %}',
            `    ${todoTemplate(DATE_FILTER_TEMPLATE)}`,
            '    {{ dateFilter(item.orderDateTime) }}',
            '    <sw-label :variant="item.primaryOrderDelivery?.stateMachineState.technicalName">',
            '        {{ item.primaryOrderDelivery?.stateMachineState.translated.name }}',
            '    </sw-label>',
            '{% endblock %}',
            '',
        ].join('\n');
        const firstRun = fixTwig(code, orderListTemplate);
        const secondRun = fixTwig(firstRun, orderListTemplate);

        expect(firstRun).toBe(expected);
        expect(secondRun).toBe(expected);
        expect(countOf(secondRun, 'TODO Codemod')).toBe(1);
    });

    it('still reports the spots that keep their comment', () => {
        const commented = `{% block a %}\n    ${todoTemplate(STORE_HINT)}\n    {{ isLoading }}\n{% endblock %}\n`;

        expect(lintTwig(commented, orderDetailTemplate)).toEqual([expect.objectContaining({ line: 3 })]);
    });
});
