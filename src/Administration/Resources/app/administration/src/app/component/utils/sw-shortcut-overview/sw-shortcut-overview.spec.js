import { nextTick } from 'vue';
import { mount } from '@vue/test-utils';

/**
 * @sw-package framework
 */
async function createWrapper(platform = 'Linux x86_64', shortcutServiceOverride = {}) {
    const shortcutService = {
        isShortcutsDisabled: jest.fn(() => false),
        setShortcutsDisabled: jest.fn(),
        ...shortcutServiceOverride,
    };

    const wrapper = mount(await wrapTestComponent('sw-shortcut-overview', { sync: true }), {
        global: {
            renderStubDefaultSlot: true,
            mocks: {
                $device: {
                    getPlatform: () => platform,
                },
            },
            provide: {
                shortcutService,
            },
            stubs: {
                'sw-shortcut-overview-item': true,
                'mt-switch': true,
                'mt-button': true,
            },
        },
    });

    return { wrapper, shortcutService };
}

describe('app/component/utils/sw-shortcut-overview', () => {
    it('should add the privilege attribute to some shortcut-overview-items', async () => {
        const { wrapper } = await createWrapper();

        wrapper.vm.showShortcutOverviewModal = true;
        await nextTick();

        const privilegeSystemClearCacheItems = wrapper.findAll(
            'sw-shortcut-overview-item-stub[privilege="system.clear_cache"]',
        );
        const privilegeSystemPluginMaintainItems = wrapper.findAll(
            'sw-shortcut-overview-item-stub[privilege="system.plugin_maintain"]',
        );

        expect(privilegeSystemClearCacheItems).toHaveLength(1);
        expect(privilegeSystemPluginMaintainItems).toHaveLength(1);
    });

    it('should only show shortcuts for the current platform', async () => {
        const { wrapper } = await createWrapper('MacIntel');

        wrapper.vm.showShortcutOverviewModal = true;
        await nextTick();

        const generalShortcuts = wrapper.vm.sections.generalShortcuts;

        expect(generalShortcuts).toEqual(
            expect.arrayContaining([
                expect.objectContaining({
                    title: 'sw-shortcut-overview.functionSpecialShortcutSaveDetailView',
                    content: 'sw-shortcut-overview.keyboardShortcutSpecialShortcutSaveDetailViewMac',
                }),
                expect.objectContaining({
                    title: 'sw-shortcut-overview.functionSpecialShortcutClearCache',
                    content: 'sw-shortcut-overview.keyboardShortcutSpecialShortcutClearCacheMac',
                }),
            ]),
        );
        expect(generalShortcuts).not.toEqual(
            expect.arrayContaining([
                expect.objectContaining({
                    content: 'sw-shortcut-overview.keyboardShortcutSpecialShortcutSaveDetailViewWindows',
                }),
                expect.objectContaining({
                    content: 'sw-shortcut-overview.keyboardShortcutSpecialShortcutSaveDetailViewLinux',
                }),
            ]),
        );
    });

    it('should show general shortcuts as the first section', async () => {
        const { wrapper } = await createWrapper();

        wrapper.vm.showShortcutOverviewModal = true;
        await nextTick();

        const generalShortcuts = wrapper.vm.sections.generalShortcuts;
        const sections = wrapper.findAll('.sw-shortcut-overview__section');

        expect(sections).toHaveLength(4);
        expect(sections.at(0).classes()).toContain('sw-shortcut-overview__section-general-shortcuts');
        expect(wrapper.find('.sw-shortcut-overview__section-advanced').exists()).toBeFalsy();
        expect(generalShortcuts).toEqual(
            expect.arrayContaining([
                expect.objectContaining({
                    title: 'sw-shortcut-overview.functionSpecialShortcutShortcutListing',
                    content: 'sw-shortcut-overview.keyboardShortcutSpecialShortcutShortcutListing',
                }),
                expect.objectContaining({
                    title: 'sw-shortcut-overview.functionSpecialShortcutFocusSearch',
                    content: 'sw-shortcut-overview.keyboardShortcutSpecialShortcutFocusSearch',
                }),
                expect.objectContaining({
                    title: 'sw-shortcut-overview.functionSpecialShortcutOpenFilters',
                    content: 'sw-shortcut-overview.keyboardShortcutSpecialShortcutOpenFilters',
                }),
                expect.objectContaining({
                    title: 'sw-shortcut-overview.functionAccessibilityCloseDialog',
                    content: 'sw-shortcut-overview.keyboardShortcutAccessibilityCloseDialog',
                }),
                expect.objectContaining({
                    title: 'sw-shortcut-overview.functionSpecialShortcutSaveDetailView',
                    content: 'sw-shortcut-overview.keyboardShortcutSpecialShortcutSaveDetailViewLinux',
                }),
                expect.objectContaining({
                    title: 'sw-shortcut-overview.functionSpecialShortcutClearCache',
                    content: 'sw-shortcut-overview.keyboardShortcutSpecialShortcutClearCacheLinux',
                }),
            ]),
        );
    });

    it('should show accessibility shortcuts in a separate section', async () => {
        const { wrapper } = await createWrapper();

        wrapper.vm.showShortcutOverviewModal = true;
        await nextTick();

        const accessibilityShortcuts = wrapper.vm.sections.accessibility;

        expect(wrapper.findAll('.sw-shortcut-overview__section')).toHaveLength(4);
        expect(accessibilityShortcuts).toEqual(
            expect.arrayContaining([
                expect.objectContaining({
                    title: 'sw-shortcut-overview.functionAccessibilitySkipToContent',
                    content: 'sw-shortcut-overview.keyboardShortcutAccessibilitySkipToContent',
                }),
                expect.objectContaining({
                    title: 'sw-shortcut-overview.functionAccessibilityMoveFocusForward',
                    content: 'sw-shortcut-overview.keyboardShortcutAccessibilityMoveFocusForward',
                }),
                expect.objectContaining({
                    title: 'sw-shortcut-overview.functionAccessibilityMoveFocusBackward',
                    content: 'sw-shortcut-overview.keyboardShortcutAccessibilityMoveFocusBackward',
                }),
            ]),
        );
        expect(accessibilityShortcuts).not.toEqual(
            expect.arrayContaining([
                expect.objectContaining({
                    title: 'sw-shortcut-overview.functionSpecialShortcutFocusSearch',
                }),
                expect.objectContaining({
                    title: 'sw-shortcut-overview.functionSpecialShortcutShortcutListing',
                }),
                expect.objectContaining({
                    title: 'sw-shortcut-overview.functionSpecialShortcutOpenFilters',
                }),
                expect.objectContaining({
                    title: 'sw-shortcut-overview.functionAccessibilityCloseDialog',
                }),
                expect.objectContaining({
                    title: 'sw-shortcut-overview.functionSpecialShortcutSaveDetailView',
                }),
                expect.objectContaining({
                    title: 'sw-shortcut-overview.functionSpecialShortcutClearCache',
                }),
            ]),
        );
    });

    it('should show the footer actions', async () => {
        const { wrapper } = await createWrapper();

        wrapper.vm.showShortcutOverviewModal = true;
        await nextTick();

        const disableShortcutsToggle = wrapper.find('mt-switch-stub');
        const closeButton = wrapper.find('mt-button-stub');

        expect(disableShortcutsToggle.attributes('label')).toBe('sw-shortcut-overview.disableShortcuts');
        expect(closeButton.attributes('variant')).toBe('secondary');
        expect(closeButton.text()).toBe('global.default.close');

        await closeButton.trigger('click');

        expect(wrapper.vm.showShortcutOverviewModal).toBe(false);
    });

    it('should toggle keyboard shortcuts through the shortcut service', async () => {
        const { wrapper, shortcutService } = await createWrapper();

        expect(wrapper.vm.shortcutsDisabled).toBe(false);
        expect(shortcutService.isShortcutsDisabled).toHaveBeenCalled();

        wrapper.vm.onToggleShortcutsDisabled(true);

        expect(wrapper.vm.shortcutsDisabled).toBe(true);
        expect(shortcutService.setShortcutsDisabled).toHaveBeenCalledWith(true);
    });

    it('should initialize the disabled shortcut state from the shortcut service', async () => {
        const { wrapper } = await createWrapper('Linux x86_64', {
            isShortcutsDisabled: jest.fn(() => true),
        });

        expect(wrapper.vm.shortcutsDisabled).toBe(true);
    });
});
