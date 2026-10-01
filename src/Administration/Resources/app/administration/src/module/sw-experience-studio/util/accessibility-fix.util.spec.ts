import type { ContentElementNode } from 'src/core/service/content-element.types';
import { applyAccessibilityFixOperations } from './accessibility-fix.util';

describe('module/sw-experience-studio/util/accessibility-fix.util', () => {
    const createLayout = (): ContentElementNode[] => [
        {
            id: 'container',
            component: 'container',
            slots: {
                default: [
                    {
                        id: 'text',
                        component: 'text',
                        properties: { text: 'Original' },
                        style: { color: 'red' },
                    } as ContentElementNode,
                ],
            },
        } as ContentElementNode,
    ];

    it('applies nested property and style operations to a child element', () => {
        const layout = createLayout();

        expect(
            applyAccessibilityFixOperations(layout, [
                {
                    type: 'set-property',
                    elementId: 'text',
                    path: 'ariaLabel',
                    value: 'Accessible text',
                },
                {
                    type: 'set-style',
                    elementId: 'text',
                    path: 'border.color',
                    value: 'blue',
                },
            ]),
        ).toBe(true);

        const text = layout[0]?.slots?.default?.[0];
        expect(text?.properties).toEqual({ text: 'Original', ariaLabel: 'Accessible text' });
        expect(text?.style).toEqual({ color: 'red', border: { color: 'blue' } });
    });

    it('removes existing values and ignores missing removal paths', () => {
        const layout = createLayout();

        expect(
            applyAccessibilityFixOperations(layout, [
                { type: 'remove-property', elementId: 'text', path: 'text' },
                { type: 'remove-style', elementId: 'text', path: 'border.color' },
            ]),
        ).toBe(true);

        const text = layout[0]?.slots?.default?.[0];
        expect(text?.properties).toEqual({});
        expect(text?.style).toEqual({ color: 'red' });
    });

    it('returns false when an operation references an unknown element or path', () => {
        const layout = createLayout();

        expect(
            applyAccessibilityFixOperations(layout, [
                { type: 'set-property', elementId: 'missing', path: 'label', value: 'x' },
            ]),
        ).toBe(false);
        expect(
            applyAccessibilityFixOperations(layout, [
                { type: 'set-property', elementId: 'text', path: '', value: 'x' },
            ]),
        ).toBe(false);
    });
});
