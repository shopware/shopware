import { renameVModelArgument } from './rename-v-model-argument';
import { createDirectiveAttribute, createFixer, createRuleApi } from './test-utils';

describe('renameVModelArgument', () => {
    it('owns default fix safety and default v-model fixer', () => {
        const usage = renameVModelArgument({ from: 'value', to: null });
        const modelAttribute = createDirectiveAttribute('model', 'value');
        modelAttribute.matchName = 'value';
        const api = createRuleApi({ usage, modelAttribute });

        usage.eslint?.report(api);
        const fix = api.reports[0].fix(createFixer());

        expect(usage.fix).toBe('auto');
        expect(fix).toEqual({ method: 'replaceText', target: modelAttribute.key, text: 'v-model' });
    });

    it('skips fixes when object v-bind can hide the replacement model prop', () => {
        const usage = renameVModelArgument({ from: 'value', to: null });
        const modelAttribute = createDirectiveAttribute('model', 'value');
        const objectVBind = createDirectiveAttribute('bind');
        modelAttribute.matchName = 'value';
        const api = createRuleApi({
            usage,
            modelAttribute,
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
                        modelAttribute,
                    ],
                },
                children: [],
            },
        });

        usage.eslint?.report(api);

        expect(api.reports[0].message).toContain('Object v-bind can hide the replacement model prop.');
        expect(api.reports[0].fix(createFixer())).toBeNull();
    });
});
