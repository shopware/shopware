import detailComponent from './index';

describe('module/sw-experience-studio/page/sw-experience-studio-detail picker', () => {
    const methods = (detailComponent as unknown as { methods: Record<string, (...args: unknown[]) => unknown> }).methods;

    it('opens the element picker anchored to the add trigger', () => {
        const anchorElement = document.createElement('button');
        const payload = {
            parentElementId: 'parent-1',
            slotName: 'content',
            anchorElement,
        };
        const vm = {
            pendingAddElementPayload: null as typeof payload | null,
            pickerAnchorElement: null as HTMLElement | null,
            isElementPickerOpen: false,
        };

        methods.onAddElement.call(vm, payload);

        expect(vm.pendingAddElementPayload).toBe(payload);
        expect(vm.pickerAnchorElement).toBe(anchorElement);
        expect(vm.isElementPickerOpen).toBe(true);
    });

    it('clears the picker anchor when the picker is closed', () => {
        const vm = {
            isElementPickerOpen: true,
            pendingAddElementPayload: {
                parentElementId: null,
                slotName: null,
                anchorElement: document.createElement('button'),
            },
            pickerAnchorElement: document.createElement('button'),
        };

        methods.onCloseElementPicker.call(vm);

        expect(vm.isElementPickerOpen).toBe(false);
        expect(vm.pendingAddElementPayload).toBeNull();
        expect(vm.pickerAnchorElement).toBeNull();
    });
});
