import toolbarComponent from './index';

describe('module/sw-experience-studio/component/sw-experience-studio-toolbar', () => {
    const methods = (toolbarComponent as unknown as { methods: Record<string, (this: Record<string, unknown>) => void> })
        .methods;

    it('opens and closes the accessibility modal', () => {
        const vm = { showAccessibilityModal: false };

        methods.onOpenAccessibilityModal.call(vm);
        expect(vm.showAccessibilityModal).toBe(true);

        methods.onCloseAccessibilityModal.call(vm);
        expect(vm.showAccessibilityModal).toBe(false);
    });
});
