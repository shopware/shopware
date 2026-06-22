import { removeSlot } from './remove-slot';
import { createFixer, createRuleApi, createSlot } from './test-utils';

describe('removeSlot', () => {
    it('owns default fix safety and generic slot removal', () => {
        const usage = removeSlot({ slot: 'actions' });
        const slot = createSlot('actions');
        const api = createRuleApi({ usage, slot });

        usage.eslint?.report(api);
        const fix = api.reports[0].fix(createFixer());

        expect(usage.fix).toBe('auto');
        expect(api.reports[0].message).toContain('"actions" API is deprecated');
        expect(fix).toEqual({
            method: 'replaceText',
            target: slot,
            text: '<!-- Slot "actions" was removed and has no replacement. -->',
        });
    });
});
