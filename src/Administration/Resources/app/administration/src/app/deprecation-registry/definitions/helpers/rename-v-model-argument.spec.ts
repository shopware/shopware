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
});
