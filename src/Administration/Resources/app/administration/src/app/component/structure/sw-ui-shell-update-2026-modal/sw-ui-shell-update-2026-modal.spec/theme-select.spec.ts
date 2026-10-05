/**
 * @sw-package framework
 */

// @deprecated tag:v6.9.0 - Will be removed together with the one-time ui-shell-update-2026 announcement modal

import { type VueWrapper } from '@vue/test-utils';
import useTheme from 'src/app/composables/use-theme';
import useModuleIconColors, { USER_MODULE_ICON_COLORS_CONFIG_KEY } from 'src/app/composables/use-module-icon-colors';
import selectMtSelectOptionByText from 'test/_helper_/select-mt-select-by-text';
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

    it('appears only on the appearance page', async () => {
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
            message: 'sw-ui-shell-update-2026-modal.pages.appearance.themeSaveError',
        });
    });
});

type ModuleIconColorsSelect = {
    props: (name: string) => unknown;
    vm: { $emit: (event: string, value: string) => void };
};

function moduleIconColorsSelect(currentWrapper: VueWrapper): ModuleIconColorsSelect {
    return currentWrapper.findComponent(
        '.sw-ui-shell-update-2026-modal__module-icon-colors-select',
    ) as unknown as ModuleIconColorsSelect;
}

describe('src/app/component/structure/sw-ui-shell-update-2026-modal - module icon colors select', () => {
    let wrapper: VueWrapper | null = null;

    beforeEach(() => {
        setIntendedAudience();
    });

    afterEach(() => {
        useModuleIconColors().enabled.value = false;

        if (wrapper) {
            wrapper.unmount();
            wrapper = null;
        }
    });

    it('appears only on the appearance page', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-ui-shell-update-2026-modal__module-icon-colors-select').exists()).toBe(false);

        await wrapper.get('.sw-ui-shell-update-2026-modal__footer-right button').trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-ui-shell-update-2026-modal__module-icon-colors-select').exists()).toBe(true);
    });

    it('shows the module icon colors preference currently in use', async () => {
        useModuleIconColors().enabled.value = true;

        wrapper = await createWrapper();
        await flushPromises();
        await wrapper.get('.sw-ui-shell-update-2026-modal__footer-right button').trigger('click');
        await flushPromises();

        expect(moduleIconColorsSelect(wrapper).props('modelValue')).toBe('module');
    });

    it('applies and persists the picked preference', async () => {
        const upsert = jest.spyOn(Shopware.Service('userConfigService'), 'upsert').mockResolvedValue({} as never);

        wrapper = await createWrapper();
        await flushPromises();
        await wrapper.get('.sw-ui-shell-update-2026-modal__footer-right button').trigger('click');
        await flushPromises();

        await selectMtSelectOptionByText(
            wrapper,
            'sw-ui-shell-update-2026-modal.pages.appearance.optionModuleIconColorsColored',
            '.sw-ui-shell-update-2026-modal__module-icon-colors-select input',
        );
        await flushPromises();

        expect(upsert).toHaveBeenCalledWith({ [USER_MODULE_ICON_COLORS_CONFIG_KEY]: { enabled: true } });
        expect(useModuleIconColors().enabled.value).toBe(true);
    });

    it('notifies the user when the preference cannot be persisted', async () => {
        jest.spyOn(Shopware.Service('userConfigService'), 'upsert').mockRejectedValue(new Error('nope'));

        wrapper = await createWrapper();
        await flushPromises();
        await wrapper.get('.sw-ui-shell-update-2026-modal__footer-right button').trigger('click');
        await flushPromises();

        const createNotificationError = jest.spyOn(
            wrapper.vm as unknown as { createNotificationError: (config: unknown) => void },
            'createNotificationError',
        );

        await selectMtSelectOptionByText(
            wrapper,
            'sw-ui-shell-update-2026-modal.pages.appearance.optionModuleIconColorsColored',
            '.sw-ui-shell-update-2026-modal__module-icon-colors-select input',
        );
        await flushPromises();

        expect(createNotificationError).toHaveBeenCalledWith({
            message: 'sw-ui-shell-update-2026-modal.pages.appearance.moduleIconColorsSaveError',
        });
    });
});
