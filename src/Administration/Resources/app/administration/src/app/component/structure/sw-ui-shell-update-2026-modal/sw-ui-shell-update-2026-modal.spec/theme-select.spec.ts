/**
 * @sw-package framework
 */

// @deprecated tag:v6.9.0 - Will be removed together with the one-time ui-shell-update-2026 announcement modal

import { type VueWrapper } from '@vue/test-utils';
import useTheme from 'src/app/composables/use-theme';
import createWrapper, { setIntendedAudience } from './create-wrapper';

// findComponent by selector loses its type, so only the parts in use are stated.
type ThemeSelect = {
    props: (name: string) => unknown;
    vm: { $emit: (event: string, value: string) => void };
};

function themeSelect(currentWrapper: VueWrapper): ThemeSelect {
    return currentWrapper.findComponent('.sw-ui-shell-update-2026-modal__theme-select') as unknown as ThemeSelect;
}

describe('src/app/component/structure/sw-ui-shell-update-2026-modal - theme select', () => {
    let wrapper: VueWrapper | null = null;

    beforeEach(() => {
        setIntendedAudience();
    });

    afterEach(() => {
        if (wrapper) {
            wrapper.unmount();
            wrapper = null;
        }
    });

    it('appears only on the dark mode page', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-ui-shell-update-2026-modal__theme-select').exists()).toBe(false);

        await wrapper.get('.sw-ui-shell-update-2026-modal__footer-right button').trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-ui-shell-update-2026-modal__theme-select').exists()).toBe(true);
    });

    it('shows the theme currently in use', async () => {
        useTheme().setTheme('dark');

        wrapper = await createWrapper();
        await flushPromises();
        await wrapper.get('.sw-ui-shell-update-2026-modal__footer-right button').trigger('click');
        await flushPromises();

        expect(themeSelect(wrapper).props('modelValue')).toBe('dark');
    });

    it('applies and persists the picked theme', async () => {
        const saveUserTheme = jest.spyOn(useTheme(), 'saveUserTheme').mockResolvedValue(undefined);

        wrapper = await createWrapper();
        await flushPromises();
        await wrapper.get('.sw-ui-shell-update-2026-modal__footer-right button').trigger('click');
        await flushPromises();

        themeSelect(wrapper).vm.$emit('update:modelValue', 'dark');
        await flushPromises();

        expect(saveUserTheme).toHaveBeenCalledWith('dark');
    });

    it('notifies the user when the theme cannot be persisted', async () => {
        jest.spyOn(useTheme(), 'saveUserTheme').mockRejectedValue(new Error('nope'));

        wrapper = await createWrapper();
        await flushPromises();
        await wrapper.get('.sw-ui-shell-update-2026-modal__footer-right button').trigger('click');
        await flushPromises();

        const createNotificationError = jest.spyOn(
            wrapper.vm as unknown as { createNotificationError: (config: unknown) => void },
            'createNotificationError',
        );

        themeSelect(wrapper).vm.$emit('update:modelValue', 'dark');
        await flushPromises();

        expect(createNotificationError).toHaveBeenCalledWith({
            message: 'sw-ui-shell-update-2026-modal.pages.darkMode.themeSaveError',
        });
    });
});
