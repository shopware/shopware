/**
 * @sw-package framework
 */

import createWrapper from './create-wrapper';

describe('app/plugins/shortcut.plugin - option shapes', () => {
    let wrapper;

    it('String: should call the onSave method', async () => {
        const onSaveMock = jest.fn();

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

        expect(onSaveMock).not.toHaveBeenCalled();

        await wrapper.trigger('keydown', {
            key: 's',
        });
        await wrapper.trigger('keydown', {
            key: 'CTRL',
        });

        expect(onSaveMock).toHaveBeenCalledWith();
    });

    it('Object with boolean active: should call the onSave method', async () => {
        const onSaveMock = jest.fn();

        wrapper = await createWrapper({
            shortcuts: {
                s: {
                    active: true,
                    method: 'onSave',
                },
            },
            methods: {
                onSave() {
                    onSaveMock();
                },
            },
        });

        expect(onSaveMock).not.toHaveBeenCalled();

        await wrapper.trigger('keydown', {
            key: 's',
        });

        expect(onSaveMock).toHaveBeenCalledWith();
    });

    it('Object with boolean active: should NOT call the onSave method', async () => {
        const onSaveMock = jest.fn();

        wrapper = await createWrapper({
            shortcuts: {
                s: {
                    active: false,
                    method: 'onSave',
                },
            },
            methods: {
                onSave() {
                    onSaveMock();
                },
            },
        });

        expect(onSaveMock).not.toHaveBeenCalled();

        await wrapper.trigger('keydown', {
            key: 's',
        });

        expect(onSaveMock).not.toHaveBeenCalledWith();
    });

    it('Object without active: should default to active and call the onSave method', async () => {
        const onSaveMock = jest.fn();

        wrapper = await createWrapper({
            shortcuts: {
                s: {
                    method: 'onSave',
                },
            },
            methods: {
                onSave() {
                    onSaveMock();
                },
            },
        });

        expect(onSaveMock).not.toHaveBeenCalled();

        await wrapper.trigger('keydown', {
            key: 's',
        });

        expect(onSaveMock).toHaveBeenCalledWith();
    });

    it('Object with function active: should call the onSave method', async () => {
        const onSaveMock = jest.fn();

        wrapper = await createWrapper({
            shortcuts: {
                s: {
                    active() {
                        return true;
                    },
                    method: 'onSave',
                },
            },
            methods: {
                onSave() {
                    onSaveMock();
                },
            },
        });

        expect(onSaveMock).not.toHaveBeenCalled();

        await wrapper.trigger('keydown', {
            key: 's',
        });

        expect(onSaveMock).toHaveBeenCalledWith();
    });

    it('Object with function active: should NOT call the onSave method', async () => {
        const onSaveMock = jest.fn();

        wrapper = await createWrapper({
            shortcuts: {
                s: {
                    active() {
                        return false;
                    },
                    method: 'onSave',
                },
            },
            methods: {
                onSave() {
                    onSaveMock();
                },
            },
        });

        expect(onSaveMock).not.toHaveBeenCalled();

        await wrapper.trigger('keydown', {
            key: 's',
        });

        expect(onSaveMock).not.toHaveBeenCalledWith();
    });

    it('Object with function active which access the vue instance: should call the onSave method', async () => {
        const onSaveMock = jest.fn();

        wrapper = await createWrapper({
            shortcuts: {
                s: {
                    active() {
                        return this.activeValue;
                    },
                    method: 'onSave',
                },
            },
            computed: {
                activeValue() {
                    return true;
                },
            },
            methods: {
                onSave() {
                    onSaveMock();
                },
            },
        });

        expect(onSaveMock).not.toHaveBeenCalled();

        await wrapper.trigger('keydown', {
            key: 's',
        });

        expect(onSaveMock).toHaveBeenCalledWith();
    });

    it('Object with function active which access the vue instance: should NOT call the onSave method', async () => {
        const onSaveMock = jest.fn();

        wrapper = await createWrapper({
            shortcuts: {
                s: {
                    active() {
                        return this.activeValue;
                    },
                    method: 'onSave',
                },
            },
            computed: {
                activeValue() {
                    return false;
                },
            },
            methods: {
                onSave() {
                    onSaveMock();
                },
            },
        });

        expect(onSaveMock).not.toHaveBeenCalled();

        await wrapper.trigger('keydown', {
            key: 's',
        });

        expect(onSaveMock).not.toHaveBeenCalledWith();
    });

    it('Object with function: function should be executed for each shortcut press', async () => {
        const onSaveMock = jest.fn();
        let shouldExecute = true;

        wrapper = await createWrapper({
            shortcuts: {
                s: {
                    active() {
                        return shouldExecute;
                    },
                    method: 'onSave',
                },
            },
            methods: {
                onSave() {
                    onSaveMock();
                },
            },
        });

        // shortcut should be executed
        expect(onSaveMock).not.toHaveBeenCalled();

        await wrapper.trigger('keydown', {
            key: 's',
        });

        expect(onSaveMock).toHaveBeenCalledWith();

        // change value dynamically
        onSaveMock.mockReset();
        shouldExecute = false;

        expect(onSaveMock).not.toHaveBeenCalled();

        await wrapper.trigger('keydown', {
            key: 's',
        });

        // shortcut should not be executed
        expect(onSaveMock).not.toHaveBeenCalledWith();
    });

    it('should pass the keydown event to the active check of a shortcut', async () => {
        const activeMock = jest.fn(() => true);
        const onToggleMock = jest.fn();

        wrapper = await createWrapper({
            shortcuts: {
                S: {
                    active: activeMock,
                    method: 'onToggle',
                },
            },
            methods: {
                onToggle() {
                    onToggleMock();
                },
            },
        });

        await wrapper.trigger('keydown', {
            key: 's',
            metaKey: true,
        });

        expect(activeMock).toHaveBeenCalledTimes(1);
        const [event] = activeMock.mock.calls[0];
        expect(event).toBeInstanceOf(KeyboardEvent);
        expect(event.key).toBe('s');
        expect(event.metaKey).toBe(true);
        expect(onToggleMock).toHaveBeenCalledTimes(1);

        wrapper.unmount();
    });
});
