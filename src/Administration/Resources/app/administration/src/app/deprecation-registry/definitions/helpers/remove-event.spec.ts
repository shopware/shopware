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

    it('skips fixes when object v-on can hide the removed event', () => {
        const usage = removeEvent({ event: 'legacy-event' });
        const eventAttribute = createDirectiveAttribute('on', 'legacy-event');
        const objectVOn = createDirectiveAttribute('on');
        eventAttribute.matchName = 'legacy-event';
        const api = createRuleApi({
            usage,
            eventAttribute,
            node: {
                name: 'mt-test',
                startTag: {
                    range: [
                        0,
                        10,
                    ],
                    loc: {
                        start: {
                            column: 0,
                        },
                    },
                    attributes: [
                        objectVOn,
                        eventAttribute,
                    ],
                },
                children: [],
            },
        });

        usage.eslint?.report(api);

        expect(api.reports[0].message).toContain('Object v-on can hide this event.');
        expect(api.reports[0].fix(createFixer())).toBeNull();
    });
});
