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

    it('marks expression-bound static value transforms as manual', () => {
        const transform = replaceWithStaticValueTransform({ value: '16px' });

        expect(transform({ valueKind: 'expression' })).toEqual({
            kind: 'replace-with-static-value',
            fix: 'manual',
            value: '16px',
            message:
                'Expression-bound prop values can be false at runtime. Review the expression and replace it with "16px" manually if needed.',
        });
    });

    it('reports transform safety messages and skips manual fixes', () => {
        const usage = renameProp({
            from: 'small',
            to: 'size',
            transform: replaceWithStaticValueTransform({ value: '16px' }),
        });
        const attribute = createDirectiveAttribute('bind', 'small');
        const api = createRuleApi({
            usage,
            attribute,
            transform: {
                kind: 'replace-with-static-value',
                fix: 'manual',
                value: '16px',
                message: 'Expression-bound prop values can be false at runtime.',
            },
        });

        usage.eslint?.report(api);

        expect(api.reports[0].message).toContain('Expression-bound prop values can be false at runtime.');
        expect(api.reports[0].fix(createFixer())).toBeNull();
    });

    it('skips fixes when object v-bind can hide the replacement prop', () => {
        const usage = renameProp({ from: 'old-prop', to: 'new-prop' });
        const attribute = createDirectiveAttribute('bind', 'old-prop');
        const objectVBind = createDirectiveAttribute('bind');
        const api = createRuleApi({
            usage,
            attribute,
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
                        objectVBind,
                        attribute,
                    ],
                },
                children: [],
            },
        });

        usage.eslint?.report(api);

        expect(api.reports[0].message).toContain('Object v-bind can hide the replacement prop.');
        expect(api.reports[0].fix(createFixer())).toBeNull();
    });
});
