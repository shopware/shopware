import { removeEvent } from './remove-event';
import { createDirectiveAttribute, createFixer, createRuleApi } from './test-utils';

describe('removeEvent', () => {
    it('owns its default fix safety and event remover', () => {
        const usage = removeEvent({ event: 'legacy-event' });
        const eventAttribute = createDirectiveAttribute('on', 'legacy-event');
        eventAttribute.matchName = 'legacy-event';
        const api = createRuleApi({ usage, eventAttribute });

        usage.eslint?.report(api);
        const fix = api.reports[0].fix(createFixer());

        expect(usage.fix).toBe('auto');
        expect(api.reports[0].message).toContain('"legacy-event" API is deprecated');
        expect(fix).toEqual({ method: 'remove', target: eventAttribute });
    });
});
