/**
 * @sw-package framework
 */

import createWrapper from './create-wrapper';

describe('app/plugins/shortcut.plugin - event guards', () => {
    let wrapper;

    it('should not call the shortcut method when keyboard shortcuts are disabled', async () => {
        const onSaveMock = jest.fn();
        const originalService = Shopware.Service.bind(Shopware);
        const serviceSpy = jest.spyOn(Shopware, 'Service').mockImplementation((serviceName) => {
            if (serviceName === 'shortcutService') {
                return {
                    isShortcutsDisabled: () => true,
                };
            }

            return originalService(serviceName);
        });

        try {
            wrapper = await createWrapper({
                shortcuts: {
                    s: 'onSave',
                },
                methods: {
                    onSave() {
                        onSaveMock();
                    },
                },
            });

            await wrapper.trigger('keydown', {
                key: 's',
            });

            expect(onSaveMock).not.toHaveBeenCalled();
        } finally {
            serviceSpy.mockRestore();
        }
    });

    it('should call the onEsc method when Escape key is pressed outside a modal', async () => {
        const onEscMock = jest.fn();

        wrapper = await createWrapper({
            shortcuts: {
                Escape: 'onEsc',
            },
            methods: {
                onEsc() {
                    onEscMock();
                },
            },
        });

        expect(onEscMock).not.toHaveBeenCalled();

        // Simulate Escape keydown event outside a modal
        await wrapper.trigger('keydown', {
            key: 'Escape',
        });

        expect(onEscMock).toHaveBeenCalledWith();
    });

    it('should NOT call the onEsc method when Escape key is pressed inside a modal', async () => {
        const onEscMock = jest.fn();

        // Create a modal element in the DOM
        const modal = document.createElement('div');
        modal.className = 'sw-modal';
        document.body.appendChild(modal);

        wrapper = await createWrapper({
            shortcuts: {
                Escape: 'onEsc',
            },
            methods: {
                onEsc() {
                    onEscMock();
                },
            },
        });

        expect(onEscMock).not.toHaveBeenCalled();

        // Simulate Escape keydown event with the target inside the modal
        const event = new KeyboardEvent('keydown', { key: 'Escape', bubbles: true });
        Object.defineProperty(event, 'target', { value: modal, enumerable: true });

        document.dispatchEvent(event);

        expect(onEscMock).not.toHaveBeenCalled();

        // Clean up
        document.body.removeChild(modal);
    });

    it('should not trigger shortcuts from inside a meteor modal', async () => {
        const onToggleMock = jest.fn();

        const modal = document.createElement('div');
        modal.className = 'mt-modal';
        document.body.appendChild(modal);

        wrapper = await createWrapper({
            shortcuts: {
                S: 'onToggle',
            },
            methods: {
                onToggle() {
                    onToggleMock();
                },
            },
        });

        const event = new KeyboardEvent('keydown', { key: 's', bubbles: true });
        Object.defineProperty(event, 'target', { value: modal, enumerable: true });

        document.dispatchEvent(event);

        expect(onToggleMock).not.toHaveBeenCalled();

        document.body.removeChild(modal);
        wrapper.unmount();
    });

    it('should ignore repeated keydown events while a key is held', async () => {
        const onToggleMock = jest.fn();

        wrapper = await createWrapper({
            shortcuts: {
                S: 'onToggle',
            },
            methods: {
                onToggle() {
                    onToggleMock();
                },
            },
        });

        await wrapper.trigger('keydown', {
            key: 's',
        });
        await wrapper.trigger('keydown', {
            key: 's',
            repeat: true,
        });

        expect(onToggleMock).toHaveBeenCalledTimes(1);

        wrapper.unmount();
    });
});
