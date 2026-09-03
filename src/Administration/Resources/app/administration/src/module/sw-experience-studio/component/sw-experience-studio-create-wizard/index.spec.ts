import createWizardComponent from './index';

describe('module/sw-experience-studio/component/sw-experience-studio-create-wizard', () => {
    const methods = (createWizardComponent as unknown as { methods: Record<string, (...args: unknown[]) => unknown> })
        .methods;
    const computed = (createWizardComponent as unknown as { computed: Record<string, (...args: unknown[]) => unknown> })
        .computed;

    it('is completable only with trimmed name and selected type', () => {
        const vm = {
            trimmedName: 'My layout',
            selectedType: 'product',
            isLoadingTypes: false,
        };

        expect(computed.isCompletable.call(vm)).toBe(true);
        expect(computed.isCompletable.call({ ...vm, trimmedName: '' })).toBe(false);
        expect(computed.isCompletable.call({ ...vm, selectedType: null })).toBe(false);
        expect(computed.isCompletable.call({ ...vm, isLoadingTypes: true })).toBe(false);
    });

    it('emits complete payload with normalized values', () => {
        const emit = jest.fn();
        const vm = {
            isCompletable: true,
            trimmedName: 'My layout',
            selectedType: 'category',
            $emit: emit,
        };

        methods.onComplete.call(vm);

        expect(emit).toHaveBeenCalledWith('complete', {
            name: 'My layout',
            type: 'category',
        });
    });
});
