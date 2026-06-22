/* eslint-disable @typescript-eslint/no-unsafe-member-access */
import { renameEvent } from './rename-event';
import { createDirectiveAttribute, createFixer, createRuleApi } from './test-utils';

describe('renameEvent', () => {
    it('owns its default fix safety and event fixer', () => {
        const usage = renameEvent({ from: 'update:value', to: 'update:model-value' });
        const eventAttribute = createDirectiveAttribute('on', 'update:value');
        eventAttribute.matchName = 'update:value';
        const api = createRuleApi({ usage, eventAttribute });

        usage.eslint?.report(api);
        const fix = api.reports[0].fix(createFixer());

        expect(usage.fix).toBe('auto');
        expect(api.reports[0].message).toContain('Use "update:model-value" instead.');
        expect(fix).toEqual({ method: 'replaceText', target: eventAttribute.key.argument, text: 'update:model-value' });
    });
});
