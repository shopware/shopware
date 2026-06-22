import { removeProp } from './remove-prop';
import { createAttribute, createFixer, createRuleApi } from './test-utils';

describe('removeProp', () => {
    it('owns defaults and runtime detection', () => {
        const usage = removeProp({ prop: 'legacy-prop' });

        expect(usage.kind).toBe('remove-prop');
        expect(usage.fix).toBe('auto');
        expect(usage.runtimeProp).toBe('legacyProp');
        expect(usage.runtime?.detect({ usedProps: { legacyProp: true } })).toBe(true);
        expect(usage.runtime?.detect({ usedProps: { 'legacy-prop': true } })).toBe(true);
        expect(usage.runtime?.detect({ usedProps: { legacyPropOther: true } })).toBe(false);
    });

    it('reports and removes matching props', () => {
        const usage = removeProp({ prop: 'legacy-prop' });
        const attribute = createAttribute('legacy-prop');
        const api = createRuleApi({ usage, attribute });

        usage.eslint?.report(api);
        const fix = api.reports[0].fix(createFixer());

        expect(api.reports[0].message).toContain('"legacy-prop" API is deprecated');
        expect(fix).toEqual({ method: 'remove', target: attribute });
    });

    it('reports transform safety messages and skips manual fixes', () => {
        const usage = removeProp({ prop: 'legacy-prop' });
        const attribute = createAttribute('legacy-prop');
        const api = createRuleApi({
            usage,
            attribute,
            transform: {
                kind: 'router-link-to-click',
                fix: 'manual',
                message: 'Object v-bind can hide router-link usage.',
            },
        });

        usage.eslint?.report(api);

        expect(api.reports[0].message).toContain('Object v-bind can hide router-link usage.');
        expect(api.reports[0].fix(createFixer())).toBeNull();
    });
});
