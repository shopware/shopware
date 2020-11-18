import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-settings/page/sw-settings-index';
import 'src/module/sw-settings/component/sw-settings-item';
import 'src/app/component/base/sw-card';
import 'src/app/component/base/sw-tabs';
import 'src/app/component/base/sw-tabs-item';


function createWrapper(privileges = [
    'store.viewer',
    'user.viewer',
    'foo.viewer',
    'snippet.viewer',
    'store.viewer',
    'listing.viewer',
    'shipping.viewer'
]) {
    const settingsItemsMock = [
        {
            group: 'system',
            to: 'sw.settings.store.index',
            icon: 'default-device-laptop',
            id: 'sw-settings-store',
            name: 'settings-store',
            label: 'c',
            privilege: 'store.viewer'
        },
        {
            group: 'system',
            to: 'sw.settings.user.list',
            icon: 'default-avatar-single',
            id: 'sw-settings-user',
            name: 'settings-user',
            label: 'a',
            privilege: 'user.viewer'
        },
        {
            group: 'system',
            to: 'sw.settings.foo.list',
            icon: 'default-avatar-single',
            id: 'sw-settings-foo',
            name: 'settings-foo',
            label: 'b',
            privilege: 'foo.viewer'
        },
        {
            group: 'shop',
            to: 'sw.settings.snippet.index',
            icon: 'default-object-globe',
            id: 'sw-settings-snippet',
            name: 'settings-snippet',
            label: 'h',
            privilege: 'snippet.viewer'
        },
        {
            group: 'shop',
            to: 'sw.settings.listing.index',
            icon: 'default-symbol-products',
            id: 'sw-settings-listing',
            name: 'settings-listing',
            label: 's',
            privilege: 'listing.viewer'
        },
        {
            group: 'shop',
            to: 'sw.settings.shipping.index',
            icon: 'default-package-open',
            id: 'sw-settings-shipping',
            name: 'settings-shipping',
            label: 'a',
            privilege: 'shipping.viewer'
        }
    ];

    settingsItemsMock.forEach((settingsItem) => {
        Shopware.State.commit('settingsItems/addItem', settingsItem);
    });

    return shallowMount(Shopware.Component.build('sw-settings-index'), {
        stubs: {
            'sw-page': {
                template: '<div><slot name="content"></slot></div>'
            },
            'sw-card-view': {
                template: '<div class="sw-card-view"><slot></slot></div>'
            },
            'sw-tabs': Shopware.Component.build('sw-tabs'),
            'sw-tabs-item': Shopware.Component.build('sw-tabs-item'),
            'sw-card': {
                template: '<div class="sw-card"><slot></slot></div>'
            },
            'sw-settings-item': Shopware.Component.build('sw-settings-item'),
            'router-link': {
                template: '<a><slot></slot></a>'
            },
            'sw-icon': {
                template: '<span></span>'
            }
        },
        provide: {
            acl: {
                can: (key) => {
                    if (!key) return true;

                    return privileges.includes(key);
                }
            }
        },
        mocks: {
            $tc: (value) => value,
            $device: { onResize: () => {} }
        }
    });
}

describe('module/sw-settings/page/sw-settings-index', () => {
    beforeEach(() => {
        Shopware.State.get('settingsItems').settingsGroups = {};
    });

    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should contain any settings items', async () => {
        const wrapper = createWrapper();
        expect(wrapper.vm.settingsGroups).not.toEqual({});
    });

    it('should return settings items alphabetically sorted', async () => {
        const wrapper = createWrapper();
        const settingsGroups = Object.entries(wrapper.vm.settingsGroups);

        settingsGroups.forEach(([, settingsItems]) => {
            settingsItems.forEach((settingsItem, index) => {
                let elementsSorted = true;

                if (index < settingsItems.length - 1) {
                    elementsSorted = (
                        settingsItems[index].label.localeCompare(settingsItems[index + 1].label) === -1
                    );
                }

                expect(elementsSorted).toEqual(true);
            });
        });
    });

    it('should render correctly', async () => {
        const wrapper = createWrapper();
        expect(wrapper.element).toMatchSnapshot();
    });

    it('should render settings items in alphabetical order', async () => {
        const wrapper = createWrapper();
        const settingsGroups = Object.entries(wrapper.vm.settingsGroups);

        settingsGroups.forEach(([settingsGroup, settingsItems]) => {
            const settingsGroupWrapper = wrapper.find(`#sw-settings__content-grid-${settingsGroup}`);
            const settingsItemsWrappers = settingsGroupWrapper.findAll('.sw-settings-item');

            // check, that all settings items were rendered
            expect(settingsItemsWrappers.length).toEqual(settingsItems.length);

            // check, that settings items were rendered in alphabetical order
            settingsItemsWrappers.wrappers.forEach((settingsItemsWrapper, index) => {
                expect(settingsItemsWrapper.attributes().id).toEqual(settingsItems[index].id);
            });
        });
    });

    it('should render settings items in alphabetical order with updated items', async () => {
        const settingsItemToAdd = {
            group: 'shop',
            to: 'sw.bar.index',
            icon: 'bar',
            id: 'sw-settings-bar',
            name: 'settings-bar',
            label: 'b'
        };

        Shopware.State.commit('settingsItems/addItem', settingsItemToAdd);

        const wrapper = createWrapper();
        const settingsGroups = Object.entries(wrapper.vm.settingsGroups);

        settingsGroups.forEach(([settingsGroup, settingsItems]) => {
            const settingsGroupWrapper = wrapper.find(`#sw-settings__content-grid-${settingsGroup}`);
            const settingsItemsWrappers = settingsGroupWrapper.findAll('.sw-settings-item');

            expect(settingsItemsWrappers.length).toEqual(settingsItems.length);

            settingsItemsWrappers.wrappers.forEach((settingsItemsWrapper, index) => {
                expect(settingsItemsWrapper.attributes().id).toEqual(settingsItems[index].id);
            });
        });
    });

    it('should add the setting to the settingsGroups in store', async () => {
        const settingsItemToAdd = {
            group: 'shop',
            to: 'sw.bar.index',
            icon: 'bar',
            id: 'sw-settings-bar',
            name: 'settings-bar',
            label: 'b'
        };

        Shopware.State.commit('settingsItems/addItem', settingsItemToAdd);

        const wrapper = createWrapper();

        const settingsGroups = wrapper.vm.settingsGroups.shop;
        const barSetting = settingsGroups.find(setting => setting.id === 'sw-settings-bar');

        expect(barSetting).toBeDefined();
    });

    it('should show the setting with the privileges', async () => {
        const settingsItemToAdd = {
            privilege: 'system.foo_bar',
            group: 'shop',
            to: 'sw.bar.index',
            icon: 'bar',
            id: 'sw-settings-bar',
            name: 'settings-bar',
            label: 'b'
        };

        Shopware.State.commit('settingsItems/addItem', settingsItemToAdd);

        const wrapper = createWrapper('system.foo_bar');

        const settingsGroups = wrapper.vm.defaultSettingsGroups.shop;
        const barSetting = settingsGroups.find(setting => setting.id === 'sw-settings-bar');

        expect(barSetting).toBeDefined();
    });

    it('should not show the setting with the privileges', async () => {
        const settingsItemToAdd = {
            privilege: 'system.foo_bar',
            group: 'shop',
            to: 'sw.bar.index',
            icon: 'bar',
            id: 'sw-settings-bar',
            name: 'settings-bar',
            label: 'b'
        };

        Shopware.State.commit('settingsItems/addItem', settingsItemToAdd);

        const wrapper = createWrapper();

        const settingsGroups = wrapper.vm.defaultSettingsGroups.shop;
        const barSetting = settingsGroups.find(setting => setting.id === 'sw-settings-bar');

        expect(barSetting).toBeUndefined();
    });

    it('should hide icon background when backgroundEnabled is false', async () => {
        const settingsItemToAdd = {
            group: 'shop',
            to: 'sw.bar.index',
            icon: 'bar',
            id: 'settings-background-disabled',
            name: 'settings-background-disabled',
            label: 'b',
            backgroundEnabled: false
        };

        Shopware.State.commit('settingsItems/addItem', settingsItemToAdd);

        const wrapper = createWrapper();

        const settingsGroups = Object.entries(wrapper.vm.settingsGroups);

        settingsGroups.forEach(([settingsGroup, settingsItems]) => {
            const settingsGroupWrapper = wrapper.find(`#sw-settings__content-grid-${settingsGroup}`);
            const settingsItemsWrappers = settingsGroupWrapper.findAll('.sw-settings-item');

            settingsItemsWrappers.wrappers.forEach((settingsItemsWrapper, index) => {
                const iconClasses = settingsItemsWrapper.find('.sw-settings-item__icon').attributes().class;

                if (settingsItems[index].backgroundEnabled === false) {
                    expect(iconClasses).not.toContain('background--enabled');
                } else {
                    expect(iconClasses).toContain('background--enabled');
                }
            });
        });
    });

    it('should hide the tab when user has no access to any settings inside the tab', async () => {
        const settingsItemToAdd = {
            privilege: 'system.foo_bar',
            group: 'shop',
            to: 'sw.bar.index',
            icon: 'bar',
            id: 'sw-settings-bar',
            name: 'settings-bar',
            label: 'b'
        };

        Shopware.State.commit('settingsItems/addItem', settingsItemToAdd);

        const wrapper = await createWrapper('system.foo_bar');

        const systemTab = wrapper.find('.sw-settings__tab-system');
        const shopTab = wrapper.find('.sw-settings__tab-shop');

        expect(systemTab.exists()).toBe(false);
        expect(shopTab.exists()).toBe(true);
    });

    it('should hide the tab when user has no access to any settings inside the tab', async () => {
        const settingsItemToAdd = {
            privilege: 'system.foo_bar',
            group: 'system',
            to: 'sw.bar.index',
            icon: 'bar',
            id: 'sw-settings-bar',
            name: 'settings-bar',
            label: 'b'
        };

        Shopware.State.commit('settingsItems/addItem', settingsItemToAdd);

        const wrapper = await createWrapper('system.foo_bar');

        const systemTab = wrapper.find('.sw-settings__tab-system');
        const shopTab = wrapper.find('.sw-settings__tab-shop');

        expect(systemTab.exists()).toBe(true);
        expect(shopTab.exists()).toBe(false);
    });
});
