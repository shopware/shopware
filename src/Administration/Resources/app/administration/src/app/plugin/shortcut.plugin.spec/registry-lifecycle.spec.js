/**
 * @sw-package framework
 */

import createWrapper from './create-wrapper';
import { mount } from '@vue/test-utils';
import { defineComponent } from 'vue';
import useShortcut from 'src/app/composables/use-shortcut';

describe('app/plugins/shortcut.plugin - registry lifecycle', () => {
    it('should still trigger shortcuts after a component was unmounted', async () => {
        const onSaveMock = jest.fn();
        const onOtherSaveMock = jest.fn();

        // Mount first component with shortcut
        const wrapper1 = await createWrapper({
            name: 'component-1',
            shortcuts: {
                'SYSTEMKEY+S': 'onSave',
            },
            methods: {
                onSave: onSaveMock,
            },
        });

        // Mount second component with another shortcut
        const wrapper2 = await createWrapper({
            name: 'component-2',
            shortcuts: {
                'SYSTEMKEY+U': 'onOtherSave',
            },
            methods: {
                onOtherSave: onOtherSaveMock,
            },
        });

        // Unmount the first component
        wrapper1.unmount();
        await flushPromises();

        // Trigger shortcut of the second (still mounted) component
        await wrapper2.trigger('keydown', {
            key: 'u',
            ctrlKey: true,
        });

        // The first component's shortcut should not be called
        expect(onSaveMock).not.toHaveBeenCalled();
        // The second component's shortcut should be called
        expect(onOtherSaveMock).toHaveBeenCalled();

        wrapper2.unmount();
    });

    it('should not trigger shortcuts from unmounted components', async () => {
        const onSaveMock = jest.fn();

        // Mount component
        const wrapperComponent = await createWrapper({
            shortcuts: {
                'SYSTEMKEY+S': 'onSave',
            },
            methods: {
                onSave: onSaveMock,
            },
        });

        // Trigger shortcut, should work
        await wrapperComponent.trigger('keydown', {
            key: 's',
            ctrlKey: true,
        });
        expect(onSaveMock).toHaveBeenCalledTimes(1);

        // Unmount component
        wrapperComponent.unmount();
        await flushPromises();

        // Trigger shortcut again on the document, should not work anymore
        const event = new KeyboardEvent('keydown', { key: 's', ctrlKey: true, bubbles: true });
        document.dispatchEvent(event);
        await flushPromises();

        expect(onSaveMock).toHaveBeenCalledTimes(1);
    });

    it('lets an option shortcut win a shared key when it registered before a composable', async () => {
        const onOption = jest.fn();
        const onComposable = jest.fn();

        // The option shortcut registers first, in the component's created hook.
        const optionWrapper = await createWrapper({
            shortcuts: {
                'SYSTEMKEY+S': 'onSave',
            },
            methods: {
                onSave: onOption,
            },
        });

        // A composable then claims the same key through the shared registry.
        const composableWrapper = mount(
            defineComponent({
                setup() {
                    useShortcut('SYSTEMKEY+S', onComposable);
                },
                template: '<div />',
            }),
        );

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 's', ctrlKey: true, bubbles: true }));

        expect(onOption).toHaveBeenCalledTimes(1);
        expect(onComposable).not.toHaveBeenCalled();

        composableWrapper.unmount();
        optionWrapper.unmount();
    });

    it('lets a composable win a shared key when it registered before an option shortcut', async () => {
        const onOption = jest.fn();
        const onComposable = jest.fn();

        // The composable registers first, in its setup.
        const composableWrapper = mount(
            defineComponent({
                setup() {
                    useShortcut('SYSTEMKEY+S', onComposable);
                },
                template: '<div />',
            }),
        );

        // An option shortcut then claims the same key.
        const optionWrapper = await createWrapper({
            shortcuts: {
                'SYSTEMKEY+S': 'onSave',
            },
            methods: {
                onSave: onOption,
            },
        });

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 's', ctrlKey: true, bubbles: true }));

        expect(onComposable).toHaveBeenCalledTimes(1);
        expect(onOption).not.toHaveBeenCalled();

        composableWrapper.unmount();
        optionWrapper.unmount();
    });
});
