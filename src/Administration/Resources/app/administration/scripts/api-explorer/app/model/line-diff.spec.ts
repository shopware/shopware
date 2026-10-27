/**
 * @sw-package framework
 */

import { diffLines, type DiffLine } from './line-diff';

const BASE = [
    '<sw-container',
    '    type="row"',
    '    class="sw-order-line-items-grid"',
    '>',
    '',
    '    {% block sw_order_line_items_grid_actions %}{% endblock %}',
    '',
    '    {% block sw_order_line_items_grid_grid %}{% endblock %}',
    '',
    '    {% block sw_order_line_items_nested_line_item_modal %}{% endblock %}',
    '',
    '</sw-container>',
].join('\n');

const HEAD = [
    '<sw-container',
    '    type="row"',
    '    class="sw-order-line-items-grid"',
    '>',
    '    {% block sw_order_line_items_grid_actions %}{% endblock %}',
    '',
    '    {% block sw_order_line_items_grid_grid %}{% endblock %}',
    '',
    '    {% block sw_order_line_items_grid_pagination %}{% endblock %}',
    '',
    '    {% block sw_order_line_items_nested_line_item_modal %}{% endblock %}',
    '</sw-container>',
].join('\n');

function reproduce(lines: DiffLine[], skip: DiffLine['type']): string {
    return lines
        .filter((line) => line.type !== skip)
        .map((line) => line.text)
        .join('\n');
}

/** Deterministic, so a failure can be replayed. */
function random(seed: number): () => number {
    let state = seed;

    return () => {
        state = (state * 1103515245 + 12345) % 2147483648;
        return state / 2147483648;
    };
}

describe('api-explorer/app/model/line-diff', () => {
    it('keeps every unchanged block placeholder of a Twig block as context', () => {
        const lines = diffLines(BASE, HEAD);

        expect(lines.filter((line) => line.type !== 'same')).toEqual([
            { type: 'removed', text: '' },
            { type: 'added', text: '    {% block sw_order_line_items_grid_pagination %}{% endblock %}' },
            { type: 'added', text: '' },
            { type: 'removed', text: '' },
        ]);
        expect(lines.filter((line) => line.type === 'same' && line.text.includes('{% block'))).toHaveLength(3);
    });

    it('reproduces both inputs from the same lines plus the removed or the added lines', () => {
        const next = random(42);
        const alphabet = [
            '',
            '',
            '    ',
            'a',
            'b',
            '{% block x %}{% endblock %}',
            '<div>',
            '</div>',
        ];
        const text = (): string =>
            Array.from({ length: Math.floor(next() * 14) }, () => alphabet[Math.floor(next() * alphabet.length)]).join('\n');

        for (let run = 0; run < 500; run += 1) {
            const before = text();
            const after = text();
            const lines = diffLines(before, after);

            expect(reproduce(lines, 'added')).toBe(before);
            expect(reproduce(lines, 'removed')).toBe(after);
        }
    });

    it('reports nothing for identical content and everything for empty sides', () => {
        expect(diffLines(BASE, BASE).every((line) => line.type === 'same')).toBe(true);
        expect(diffLines(null, 'a\nb')).toEqual([
            { type: 'added', text: 'a' },
            { type: 'added', text: 'b' },
        ]);
    });
});
