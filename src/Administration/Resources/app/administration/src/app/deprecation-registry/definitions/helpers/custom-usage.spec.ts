import { customUsage } from './custom-usage';
import { createFixer, createRuleApi } from './test-utils';

describe('customUsage', () => {
    it('owns manual default safety', () => {
        const usage = customUsage({ name: 'unknown-custom' });

        expect(usage.kind).toBe('custom');
        expect(usage.fix).toBe('manual');
    });

    it('owns floating-ui default-opened detection and fixer', () => {
        const usage = customUsage({
            name: 'floating-ui-default-opened',
            fix: 'auto',
            message: 'Add :is-opened="true".',
        });
        const node = {
            name: 'mt-floating-ui',
            startTag: {
                range: [0, 16],
                attributes: [],
            },
            children: [],
        };
        const api = createRuleApi({ usage, node });

        usage.eslint?.report(api);
        const fix = api.reports[0].fix(createFixer());

        expect(api.reports[0].message).toContain('Add :is-opened="true".');
        expect(fix).toEqual({ method: 'insertTextAfterRange', range: [15, 15], text: ' :is-opened="true"' });
    });
});
