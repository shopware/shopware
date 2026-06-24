import { slotToPropComment } from './slot-to-prop-comment';
import { createFixer, createRuleApi, createSlot } from './test-utils';

describe('slotToPropComment', () => {
    it('adds a TODO comment before the deprecated slot', () => {
        const usage = slotToPropComment({ slot: 'default', prop: 'options' });
        const slot = createSlot('default');
        const api = createRuleApi({ usage, slot });

        usage.eslint?.report(api);

        expect(api.reports[0].fix(createFixer())).toEqual({
            method: 'insertTextBefore',
            target: slot.startTag,
            text: '<!-- TODO Codemod: Remove the "default" slot and use the "options" prop instead -->\n',
        });
    });
});
