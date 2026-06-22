import { mapPropValue } from './map-prop-value';
import { createAttribute, createFixer, createRuleApi } from './test-utils';

describe('mapPropValue', () => {
    it('owns defaults and runtime detection', () => {
        const usage = mapPropValue({ prop: 'variant', from: 'old', to: 'new' });

        expect(usage.kind).toBe('map-prop-value');
        expect(usage.fix).toBe('auto');
        expect(usage.runtimeProp).toBe('variant');
        expect(usage.runtime?.detect({ usedProps: { variant: 'old' } })).toBe(true);
        expect(usage.runtime?.detect({ usedProps: { 'variant': 'old' } })).toBe(true);
        expect(usage.runtime?.detect({ usedProps: { variant: 'new' } })).toBe(false);
    });

    it('reports and fixes matching static values', () => {
        const usage = mapPropValue({ prop: 'variant', from: 'old', to: 'new' });
        const value = { value: 'old' };
        const attribute = createAttribute('variant', value);
        const api = createRuleApi({ usage, attribute });

        usage.eslint?.report(api);
        const fix = api.reports[0].fix(createFixer());

        expect(api.reports[0].message).toContain('Use "new" instead.');
        expect(fix).toEqual([{ method: 'replaceText', target: value, text: '"new"' }]);
    });
});
