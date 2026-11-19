import { editingLanguageChain } from './element-settings.util';

describe('frame language fallback', () => {
    it('resolves the selected language before its parent and the system language', () => {
        expect(editingLanguageChain('german', 'parent')).toEqual(['german', 'parent', Shopware.Defaults.systemLanguageId]);
    });

    it('includes the system language only once when it is the selected language or parent', () => {
        const system = Shopware.Defaults.systemLanguageId;
        expect(editingLanguageChain()).toEqual([system]);
        expect(editingLanguageChain('german', system)).toEqual(['german', system]);
        expect(editingLanguageChain('german', 'german')).toEqual(['german', system]);
    });
});
