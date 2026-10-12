import { removeSlot } from './index';
import { createFixer, createRuleApi, createSlot } from '../test-utils';

describe('removeSlot', () => {
    it('auto-fixes a removed slot by replacing it with a comment that quotes its content', () => {
        const usage = removeSlot({ slot: 'hint' });
        const slot = createSlot('hint');
        const api = createRuleApi({ usage, slot });

        usage.eslint?.report(api);
        const fix = api.reports[0].fix(createFixer());

        expect(usage.fix).toBe('auto');
        expect(api.reports[0].message).toContain('"hint" API is deprecated');
        expect(fix).toEqual({
            method: 'replaceText',
            target: slot,
            text: '<!-- Slot "hint" was removed without replacement. Previous value was: Slot content -->',
        });
    });

    it('replaces a removed slot with the configured replacement comment', () => {
        const usage = removeSlot({
            slot: 'actions',
            slotReplacementComment: 'Slot "actions" was removed and has no replacement.',
        });
        const slot = createSlot('actions');
        const api = createRuleApi({ usage, slot });

        usage.eslint?.report(api);
        const fix = api.reports[0].fix(createFixer());

        expect(fix).toEqual({
            method: 'replaceText',
            target: slot,
            text: '<!-- Slot "actions" was removed and has no replacement. -->',
        });
    });

    it('keeps the slot and puts the configured review hint above it', () => {
        const usage = removeSlot({ slot: 'content', fix: 'unsafe-auto', slotReviewHint: 'Move the content out.' });
        const slot = createSlot('content');
        const api = createRuleApi({ usage, slot });

        usage.eslint?.report(api);
        const fix = api.reports[0].fix(createFixer());

        expect(fix).toEqual({
            method: 'insertTextBeforeRange',
            range: [
                3,
                4,
            ],
            text: `<!-- TODO Codemod: Move the content out. -->\n${' '.repeat(8)}`,
        });
    });

    it('skips a usage that already carries the configured review hint', () => {
        const usage = removeSlot({ slot: 'content', slotReviewHint: 'Move the content out.' });
        const api = createRuleApi({ usage, slot: createSlot('content') });
        const hasCodemodComment = jest.fn(() => true);
        api.ast.hasCodemodComment = hasCodemodComment;

        usage.eslint?.report(api);

        expect(hasCodemodComment).toHaveBeenCalledWith(api.node, 'Move the content out.');
        expect(api.reports).toHaveLength(0);
    });
});
