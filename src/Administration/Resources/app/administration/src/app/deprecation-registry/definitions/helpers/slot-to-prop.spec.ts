/* eslint-disable @typescript-eslint/no-unsafe-member-access -- assertions read fields of untyped ESLint report descriptors */
import { slotToProp } from './slot-to-prop';
import { createFixer, createRuleApi, createSlot } from './test-utils';

describe('slotToProp', () => {
    it('auto-fixes a slot moved to a prop by replacing it with a comment naming the prop', () => {
        const usage = slotToProp({ slot: 'label', prop: 'label' });
        const slot = createSlot('label');
        const api = createRuleApi({ usage, slot });

        usage.eslint?.report(api);
        const fix = api.reports[0].fix(createFixer());

        expect(usage.fix).toBe('auto');
        expect(api.reports[0].message).toContain('"label" API is deprecated');
        expect(fix.text).toContain('should be replaced with "label" prop');
    });

    it('keeps the slot and puts the configured review hint above it', () => {
        const usage = slotToProp({ slot: 'label', prop: 'label', slotReviewHint: 'Use the "label" prop.' });
        const slot = createSlot('label');
        const api = createRuleApi({ usage, slot });

        usage.eslint?.report(api);
        const fix = api.reports[0].fix(createFixer());

        expect(fix).toEqual({
            method: 'insertTextBefore',
            target: slot.startTag,
            text: '<!-- TODO Codemod: Use the "label" prop. -->\n',
        });
    });

    it('skips a usage that already carries the configured review hint', () => {
        const usage = slotToProp({ slot: 'label', prop: 'label', slotReviewHint: 'Use the "label" prop.' });
        const api = createRuleApi({ usage, slot: createSlot('label') });
        const hasCodemodComment = jest.fn(() => true);
        api.ast.hasCodemodComment = hasCodemodComment;

        usage.eslint?.report(api);

        expect(hasCodemodComment).toHaveBeenCalledWith(api.node, 'Use the "label" prop.');
        expect(api.reports).toHaveLength(0);
    });
});
