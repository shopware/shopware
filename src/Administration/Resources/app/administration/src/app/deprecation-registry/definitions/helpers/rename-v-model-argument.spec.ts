import { renameVModelArgument } from './rename-v-model-argument';
import { createDirectiveAttribute, createFixer, createRuleApi } from './test-utils';

describe('renameVModelArgument', () => {
    it('auto-fixes a v-model argument renamed to the default model into a plain v-model', () => {
        const usage = renameVModelArgument({ from: 'value', to: null });
        const modelAttribute = createDirectiveAttribute('model', 'value');
        modelAttribute.matchName = 'value';
        const api = createRuleApi({ usage, modelAttribute });

        usage.eslint?.report(api);
        const fix = api.reports[0].fix(createFixer());

        expect(usage.fix).toBe('auto');
        expect(fix).toEqual({ method: 'replaceText', target: modelAttribute.key, text: 'v-model' });
    });

    it('keeps the modifiers when a v-model argument becomes a plain v-model', () => {
        const usage = renameVModelArgument({ from: 'value', to: null });
        const modelAttribute = createDirectiveAttribute('model', 'value', null, ['trim']);
        modelAttribute.matchName = 'value';
        const api = createRuleApi({ usage, modelAttribute });

        usage.eslint?.report(api);
        const fix = api.reports[0].fix(createFixer());

        expect(fix).toEqual({ method: 'replaceText', target: modelAttribute.key, text: 'v-model.trim' });
    });

    it('keeps the modifiers when a plain v-model gets an argument', () => {
        const usage = renameVModelArgument({ from: null, to: 'checked' });
        const modelAttribute = createDirectiveAttribute('model', undefined, null, ['number']);
        modelAttribute.matchName = null;
        const api = createRuleApi({ usage, modelAttribute });

        usage.eslint?.report(api);
        const fix = api.reports[0].fix(createFixer());

        expect(fix).toEqual({ method: 'replaceText', target: modelAttribute.key, text: 'v-model:checked.number' });
    });

    it('keeps all modifiers when only the v-model argument is renamed', () => {
        const usage = renameVModelArgument({ from: 'value', to: 'checked' });
        const modelAttribute = createDirectiveAttribute('model', 'value', null, [
            'lazy',
            'trim',
        ]);
        modelAttribute.matchName = 'value';
        const api = createRuleApi({ usage, modelAttribute });

        usage.eslint?.report(api);
        const fix = api.reports[0].fix(createFixer());

        expect(fix).toEqual({ method: 'replaceText', target: { name: 'value' }, text: 'checked' });
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
