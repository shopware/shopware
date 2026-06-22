/* eslint-disable @typescript-eslint/no-unsafe-member-access */
import { replaceWithStaticValueTransform, renameProp } from './index';
import { createAttribute, createDirectiveAttribute, createFixer, createRuleApi } from './test-utils';

describe('renameProp', () => {
    it('owns defaults and runtime detection', () => {
        const usage = renameProp({ from: 'old-prop', to: 'new-prop' });

        expect(usage.kind).toBe('rename-prop');
        expect(usage.fix).toBe('auto');
        expect(usage.runtimeProp).toBe('oldProp');
        expect(usage.runtime?.detect({ usedProps: { oldProp: true } })).toBe(true);
        expect(usage.runtime?.detect({ usedProps: { 'old-prop': true } })).toBe(true);
        expect(usage.runtime?.detect({ usedProps: { oldPropOther: true } })).toBe(false);
    });

    it('reports and fixes bound prop renames', () => {
        const usage = renameProp({ from: 'old-prop', to: 'new-prop' });
        const attribute = createDirectiveAttribute('bind', 'old-prop');
        const api = createRuleApi({ usage, attribute });

        usage.eslint?.report(api);
        const fixer = createFixer();
        const fix = api.reports[0].fix(fixer);

        expect(api.reports[0].message).toContain('Use "new-prop" instead.');
        expect(fix).toEqual({ method: 'replaceText', target: attribute.key.argument, text: 'new-prop' });
    });

    it('fixes static value transforms without treating itself as a conflict', () => {
        const usage = renameProp({
            from: 'small',
            to: 'size',
            transform: replaceWithStaticValueTransform({ value: '16px' }),
        });
        const attribute = createAttribute('small');
        const api = createRuleApi({ usage, attribute });

        usage.eslint?.report(api);
        const fix = api.reports[0].fix(createFixer());

        expect(fix).toEqual({ method: 'replaceText', target: attribute, text: 'size="16px"' });
    });
});
