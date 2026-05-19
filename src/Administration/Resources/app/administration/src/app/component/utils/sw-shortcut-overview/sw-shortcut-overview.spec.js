import { mount } from '@vue/test-utils';

/**
 * @sw-package framework
 */

describe('app/component/utils/sw-shortcut-overview', () => {
    let wrapper;
    let shortcutService;

    async function createWrapper(platform = 'Linux x86_64', shortcutServiceOverride = {}) {
        shortcutService = {
            isShortcutsDisabled: jest.fn(() => false),
            setShortcutsDisabled: jest.fn(),
            ...shortcutServiceOverride,
        };

        wrapper = mount(await wrapTestComponent('sw-shortcut-overview', { sync: true }), {
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
                    'sw-modal': {
                        template: '<div class="sw-modal-stub"><slot></slot><slot name="modal-footer"></slot></div>',
                    },
                    'sw-shortcut-overview-item': true,
                    'mt-switch': {
                        name: 'mt-switch',
                        props: [
                            'modelValue',
                            'label',
                        ],
                        template: '<div class="mt-switch-stub">{{ label }}</div>',
                    },
                    'mt-button': {
                        name: 'mt-button',
                        props: [
                            'variant',
                        ],
                        emits: ['click'],
                        template: '<button class="mt-button-stub" @click="$emit(\'click\')"><slot></slot></button>',
                    },
                },
            },
        });
    }

    beforeEach(async () => {
        await createWrapper();
    });

    it('should add the privilege attribute to some shortcut-overview-items', async () => {
        await wrapper.setData({
            showShortcutOverviewModal: true,
        });

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
        await createWrapper('MacIntel');
        await wrapper.setData({
            showShortcutOverviewModal: true,
        });

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
        await wrapper.setData({
            showShortcutOverviewModal: true,
        });

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
        await wrapper.setData({
            showShortcutOverviewModal: true,
        });

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
        await wrapper.setData({
            showShortcutOverviewModal: true,
        });

        const disableShortcutsToggle = wrapper.findComponent({ name: 'mt-switch' });
        const closeButton = wrapper.findComponent({ name: 'mt-button' });

        expect(disableShortcutsToggle.props('modelValue')).toBe(false);
        expect(disableShortcutsToggle.props('label')).toBe('sw-shortcut-overview.disableShortcuts');
        expect(closeButton.props('variant')).toBe('secondary');
        expect(closeButton.text()).toBe('global.default.close');

        await closeButton.trigger('click');

        expect(wrapper.vm.showShortcutOverviewModal).toBe(false);
    });

    it('should toggle keyboard shortcuts through the shortcut service', async () => {
        expect(wrapper.vm.shortcutsDisabled).toBe(false);
        expect(shortcutService.isShortcutsDisabled).toHaveBeenCalled();

        wrapper.vm.onToggleShortcutsDisabled(true);

        expect(wrapper.vm.shortcutsDisabled).toBe(true);
        expect(shortcutService.setShortcutsDisabled).toHaveBeenCalledWith(true);
    });

    it('should initialize the disabled shortcut state from the shortcut service', async () => {
        await createWrapper('Linux x86_64', {
            isShortcutsDisabled: jest.fn(() => true),
        });

        expect(wrapper.vm.shortcutsDisabled).toBe(true);
    });
});
