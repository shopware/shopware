/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import { defineComponent, ref } from 'vue';
import useShortcut from './use-shortcut';

function pressKey(key: string, ctrlKey = false): void {
    document.dispatchEvent(new KeyboardEvent('keydown', { key, ctrlKey, bubbles: true }));
}

function mountWithShortcut(setup: () => Record<string, unknown> | void): { unmount: () => void } {
    return mount(defineComponent({ template: '<div />', setup })) as unknown as { unmount: () => void };
}

describe('src/app/composables/use-shortcut', () => {
    // getSystemKey() reads the platform, and SYSTEMKEY is CTRL only on macOS.
    beforeAll(() => {
        Object.defineProperty(window.navigator, 'platform', { value: 'MacIntel', configurable: true });
    });

    it('fires the handler for its key', () => {
        const onSave = jest.fn();

        mountWithShortcut(() => {
            useShortcut('SYSTEMKEY+S', onSave);
        });

        pressKey('s', true);

        expect(onSave).toHaveBeenCalledTimes(1);
    });

    it('stops firing once the component is unmounted', () => {
        const onSave = jest.fn();

        const wrapper = mountWithShortcut(() => {
            useShortcut('SYSTEMKEY+S', onSave);
        });

        pressKey('s', true);
        wrapper.unmount();
        pressKey('s', true);

        expect(onSave).toHaveBeenCalledTimes(1);
    });

    it('leaves another component registered for the same key alone', () => {
        const onPageSave = jest.fn();
        const onPanelSave = jest.fn();

        mountWithShortcut(() => {
            useShortcut('SYSTEMKEY+S', onPageSave);
        });
        const panel = mountWithShortcut(() => {
            useShortcut('SYSTEMKEY+S', onPanelSave);
        });

        panel.unmount();
        pressKey('s', true);

        expect(onPageSave).toHaveBeenCalledTimes(1);
        expect(onPanelSave).not.toHaveBeenCalled();
    });

    it('re-evaluates a reactive active option on every keystroke', () => {
        const onSave = jest.fn();
        const canSave = ref(false);

        mountWithShortcut(() => {
            useShortcut('SYSTEMKEY+S', onSave, { active: () => canSave.value });
        });

        pressKey('s', true);
        expect(onSave).not.toHaveBeenCalled();

        canSave.value = true;
        pressKey('s', true);

        expect(onSave).toHaveBeenCalledTimes(1);
    });

    it('honours a constant active option', () => {
        const onSave = jest.fn();

        mountWithShortcut(() => {
            useShortcut('SYSTEMKEY+S', onSave, { active: false });
        });

        pressKey('s', true);

        expect(onSave).not.toHaveBeenCalled();
    });

    it('warns and registers nothing when called outside setup', () => {
        const warn = jest.spyOn(Shopware.Utils.debug, 'warn').mockImplementation(() => {});
        const onSave = jest.fn();

        useShortcut('SYSTEMKEY+S', onSave);
        pressKey('s', true);

        expect(warn).toHaveBeenCalledWith('useShortcut', expect.stringContaining('during setup'));
        expect(onSave).not.toHaveBeenCalled();

        warn.mockRestore();
    });
});
