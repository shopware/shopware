/**
 * @sw-package framework
 */

import createWrapper from './create-wrapper';

describe('app/plugins/shortcut.plugin - key sequences', () => {
    let wrapper;

    it('String sequence: should call the shortcut method', async () => {
        const openFiltersMock = jest.fn();

        wrapper = await createWrapper({
            shortcuts: {
                OF: 'openFilters',
            },
            methods: {
                openFilters() {
                    openFiltersMock();
                },
            },
        });

        await wrapper.trigger('keydown', {
            key: 'o',
        });

        expect(openFiltersMock).not.toHaveBeenCalled();

        await wrapper.trigger('keydown', {
            key: 'f',
        });

        expect(openFiltersMock).toHaveBeenCalledTimes(1);

        wrapper.unmount();
    });

    it('String sequence: should prefer the shortcut sequence over a single key shortcut', async () => {
        const openFiltersMock = jest.fn();
        const focusSearchMock = jest.fn();

        wrapper = await createWrapper({
            shortcuts: {
                f: 'focusSearch',
                OF: 'openFilters',
            },
            methods: {
                focusSearch() {
                    focusSearchMock();
                },

                openFilters() {
                    openFiltersMock();
                },
            },
        });

        await wrapper.trigger('keydown', {
            key: 'o',
        });
        await wrapper.trigger('keydown', {
            key: 'f',
        });

        expect(openFiltersMock).toHaveBeenCalledTimes(1);
        expect(focusSearchMock).not.toHaveBeenCalled();

        await wrapper.trigger('keydown', {
            key: 'f',
        });

        expect(focusSearchMock).toHaveBeenCalledTimes(1);
    });

    it('should not trigger a single key shortcut while a navigation shortcut sequence is typed', async () => {
        const onToggleMock = jest.fn();
        const shortcutFactory = Shopware.Application.getContainer('factory').shortcut;
        shortcutFactory.register('GS', '/sw/settings/index');

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
            key: 'g',
        });
        await wrapper.trigger('keydown', {
            key: 's',
        });

        expect(onToggleMock).not.toHaveBeenCalled();

        await wrapper.trigger('keydown', {
            key: 'x',
        });
        await wrapper.trigger('keydown', {
            key: 's',
        });

        expect(onToggleMock).toHaveBeenCalledTimes(1);

        shortcutFactory.getShortcutRegistry().clear();
        wrapper.unmount();
    });

    it('should trigger the standalone shortcut again once the sequence delay has passed', async () => {
        const onToggleMock = jest.fn();
        const shortcutFactory = Shopware.Application.getContainer('factory').shortcut;
        shortcutFactory.register('GS', '/sw/settings/index');

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

        jest.useFakeTimers();

        await wrapper.trigger('keydown', {
            key: 'g',
        });
        jest.advanceTimersByTime(1000);
        await wrapper.trigger('keydown', {
            key: 's',
        });

        expect(onToggleMock).toHaveBeenCalledTimes(1);

        jest.useRealTimers();
        shortcutFactory.getShortcutRegistry().clear();
        wrapper.unmount();
    });

    it('should still trigger a single key shortcut when a non system modifier key is pressed', async () => {
        const onFocusMock = jest.fn();

        wrapper = await createWrapper({
            shortcuts: {
                f: 'onFocus',
            },
            methods: {
                onFocus() {
                    onFocusMock();
                },
            },
        });

        await wrapper.trigger('keydown', {
            key: 'f',
            metaKey: true,
        });

        expect(onFocusMock).toHaveBeenCalledTimes(1);

        wrapper.unmount();
    });

    it('should not start a key sequence when a modifier key is pressed', async () => {
        const onCycleMock = jest.fn();

        wrapper = await createWrapper({
            shortcuts: {
                CT: 'onCycle',
            },
            methods: {
                onCycle() {
                    onCycleMock();
                },
            },
        });

        await wrapper.trigger('keydown', {
            key: 'c',
            metaKey: true,
        });
        await wrapper.trigger('keydown', {
            key: 't',
        });

        expect(onCycleMock).not.toHaveBeenCalled();

        await wrapper.trigger('keydown', {
            key: 'c',
        });
        await wrapper.trigger('keydown', {
            key: 't',
        });

        expect(onCycleMock).toHaveBeenCalledTimes(1);

        wrapper.unmount();
    });
});
