/* eslint-disable sw-test-rules/test-file-max-lines-warning */

/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';

async function createWrapper(
    privileges = [
        'store.viewer',
        'user.viewer',
        'foo.viewer',
        'snippet.viewer',
        'store.viewer',
        'listing.viewer',
        'shipping.viewer',
    ],
) {
    const settingsItemsMock = [
        {
            group: 'system',
            to: 'sw.settings.store.index',
            icon: 'regular-laptop',
            id: 'sw-settings-store',
            name: 'settings-store',
            label: 'Storefront',
            privilege: 'store.viewer',
        },
        {
            group: 'system',
            to: 'sw.settings.user.list',
            icon: 'regular-user',
            id: 'sw-settings-user',
            name: 'settings-user',
            label: 'Users & Permissions',
            privilege: 'user.viewer',
        },
        {
            group: 'system',
            to: 'sw.settings.foo.list',
            icon: 'regular-user',
            id: 'sw-settings-foo',
            name: 'settings-foo',
            label: "User's Foo & Bar",
            privilege: 'foo.viewer',
        },
        {
            group: 'shop',
            to: 'sw.settings.snippet.index',
            icon: 'regular-globe',
            id: 'sw-settings-snippet',
            name: 'settings-snippet',
            label: 'Snippets',
            privilege: 'snippet.viewer',
        },
        {
            group: 'shop',
            to: 'sw.settings.listing.index',
            icon: 'regular-products',
            id: 'sw-settings-listing',
            name: 'settings-listing',
            label: 'Listings',
            privilege: 'listing.viewer',
        },
        {
            group: 'shop',
            to: 'sw.settings.shipping.index',
            icon: 'regular-truck',
            id: 'sw-settings-shipping',
            name: 'settings-shipping',
            label: 'Shipping',
            privilege: 'shipping.viewer',
        },
        {
            group: 'plugins',
            to: {
                name: 'sw.extension.sdk.index',
                params: {
                    id: Shopware.Utils.createId(),
                },
            },
            icon: 'regular-books',
            id: 'sw-extension-books',
            name: 'settings-app-book',
            label: {
                translated: true,
                label: 'extension-sdk',
            },
        },
        {
            group: 'plugins',
            to: {
                name: 'sw.extension.sdk.index',
                params: {
                    id: Shopware.Utils.createId(),
                },
            },
            icon: 'regular-books',
            id: 'sw-extension-briefcase',
            name: 'settings-app-briefcase',
            label: {
                translated: false,
                label: 'general.no',
            },
        },
    ];

    settingsItemsMock.forEach((settingsItem) => {
        Shopware.Store.get('settingsItems').addItem(settingsItem);
    });

    return mount(
        await wrapTestComponent('sw-settings-index', {
            sync: true,
        }),
        {
            global: {
                mocks: {
                    $t: (path) => {
                        if (typeof path !== 'string') {
                            return `${path}`;
                        }
                        return path;
                    },
                },
                stubs: {
                    'sw-page': {
                        template: '<div><slot name="content"></slot></div>',
                    },
                    'sw-card-view': {
                        template: '<div class="sw-card-view"><slot></slot></div>',
                    },
                    'sw-tabs': await wrapTestComponent('sw-tabs'),
                    'sw-tabs-deprecated': await wrapTestComponent('sw-tabs-deprecated', { sync: true }),
                    'sw-tabs-item': await wrapTestComponent('sw-tabs-item'),
                    'sw-settings-item': await wrapTestComponent('sw-settings-item'),
                    'mt-search': {
                        template: '<div class="mt-search"><slot></slot></div>',
                    },
                    'sw-highlight-text': await wrapTestComponent('sw-highlight-text'),
                    'router-link': {
                        template: '<a><slot></slot></a>',
                    },
                    'sw-extension-component-section': true,
                    'sw-dismissible-notices': true,
                },
                provide: {
                    acl: {
                        can: (key) => {
                            if (!key) return true;

                            return privileges.includes(key);
                        },
                    },
                },
            },
        },
    );
}

describe('module/sw-settings/page/sw-settings-index', () => {
    beforeEach(async () => {
        Shopware.Store.get('settingsItems').settingsGroups = {};
        jest.spyOn(Shopware.Service('userConfigService'), 'search').mockResolvedValue({ data: {} });
        jest.spyOn(Shopware.Service('userConfigService'), 'upsert').mockResolvedValue();
    });

    afterEach(() => {
        jest.restoreAllMocks();
    });

    it('should contain any settings items', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm.settingsGroups).not.toEqual({});
    });

    it('should return settings items alphabetically sorted', async () => {
        const wrapper = await createWrapper();
        const settingsGroups = Object.entries(wrapper.vm.settingsGroups);

        settingsGroups.forEach(
            ([
                ,
                settingsItems,
            ]) => {
                settingsItems.forEach((settingsItem, index) => {
                    let elementsSorted = true;

                    if (index < settingsItems.length - 1 && typeof settingsItems[index].label === 'string') {
                        elementsSorted = settingsItems[index].label.localeCompare(settingsItems[index + 1].label) === -1;
                    }

                    expect(elementsSorted).toBe(true);
                });
            },
        );
    });

    it('should render settings items in alphabetical order', async () => {
        const wrapper = await createWrapper();
        await flushPromises();
        const settingsGroups = Object.entries(wrapper.vm.settingsGroups);

        settingsGroups.forEach(
            ([
                settingsGroup,
                settingsItems,
            ]) => {
                const settingsGroupWrapper = wrapper.find(`#sw-settings__content-group-${settingsGroup}`);
                const settingsItemsWrappers = settingsGroupWrapper.findAll('.sw-settings-item');

                // check, that all settings items were rendered
                expect(settingsItemsWrappers).toHaveLength(settingsItems.length);

                // check, that settings items were rendered in alphabetical order
                settingsItemsWrappers.forEach((settingsItemsWrapper, index) => {
                    expect(settingsItemsWrapper.attributes().id).toEqual(settingsItems[index].id);
                });
            },
        );
    });

    it('should render settings items in alphabetical order with updated items', async () => {
        const settingsItemToAdd = {
            group: 'shop',
            to: 'sw.bar.index',
            icon: 'regular-storefront',
            id: 'sw-settings-bar',
            name: 'settings-bar',
            label: 'b',
        };

        Shopware.Store.get('settingsItems').addItem(settingsItemToAdd);

        const wrapper = await createWrapper();
        await flushPromises();
        const settingsGroups = Object.entries(wrapper.vm.settingsGroups);

        settingsGroups.forEach(
            ([
                settingsGroup,
                settingsItems,
            ]) => {
                const settingsGroupWrapper = wrapper.find(`#sw-settings__content-group-${settingsGroup}`);
                const settingsItemsWrappers = settingsGroupWrapper.findAll('.sw-settings-item');

                expect(settingsItemsWrappers).toHaveLength(settingsItems.length);

                settingsItemsWrappers.forEach((settingsItemsWrapper, index) => {
                    expect(settingsItemsWrapper.attributes().id).toEqual(settingsItems[index].id);
                });
            },
        );
    });

    it('should add the setting to the settingsGroups in store', async () => {
        const settingsItemToAdd = {
            group: 'shop',
            to: 'sw.bar.index',
            icon: 'regular-storefront',
            id: 'sw-settings-bar',
            name: 'settings-bar',
            label: 'b',
        };

        Shopware.Store.get('settingsItems').addItem(settingsItemToAdd);

        const wrapper = await createWrapper();

        const settingsGroups = wrapper.vm.settingsGroups.shop;
        const barSetting = settingsGroups.find((setting) => setting.id === 'sw-settings-bar');

        expect(barSetting).toBeDefined();
    });

    it('should show the setting with the privileges', async () => {
        const settingsItemToAdd = {
            privilege: 'system.foo_bar',
            group: 'shop',
            to: 'sw.bar.index',
            icon: 'regular-storefront',
            id: 'sw-settings-bar',
            name: 'settings-bar',
            label: 'b',
        };

        Shopware.Store.get('settingsItems').addItem(settingsItemToAdd);

        const wrapper = await createWrapper('system.foo_bar');

        const settingsGroups = wrapper.vm.settingsGroups.shop;
        const barSetting = settingsGroups.find((setting) => setting.id === 'sw-settings-bar');

        expect(barSetting).toBeDefined();
    });

    it('should not show the setting with the privileges', async () => {
        const settingsItemToAdd = {
            privilege: 'system.foo_bar',
            group: 'shop',
            to: 'sw.bar.index',
            icon: 'regular-storefront',
            id: 'sw-settings-bar',
            name: 'settings-bar',
            label: 'b',
        };

        Shopware.Store.get('settingsItems').addItem(settingsItemToAdd);

        const wrapper = await createWrapper();

        const settingsGroups = wrapper.vm.settingsGroups.shop;
        const barSetting = settingsGroups.find((setting) => setting.id === 'sw-settings-bar');

        expect(barSetting).toBeUndefined();
    });

    it('should correctly resolve dynamic group functions and add the item', async () => {
        const settingsItemToAdd = {
            group: () => 'dynamicGroup',
            to: 'sw.dynamic.index',
            icon: 'regular-storefront',
            id: 'sw-dynamic-setting',
            name: 'settings-dynamic',
            label: 'Dynamic Setting',
        };

        Shopware.Store.get('settingsItems').addItem(settingsItemToAdd);

        const wrapper = await createWrapper();
        await flushPromises();

        const dynamicGroup = wrapper.vm.settingsGroups.dynamicGroup;
        expect(dynamicGroup).toBeDefined();
        expect(dynamicGroup).toHaveLength(1);
        expect(dynamicGroup[0]).toEqual(settingsItemToAdd);
    });

    it('should display settings items based on user privileges', async () => {
        const settingsItemToAdd = {
            privilege: 'system.foo_bar',
            group: 'shop',
            to: 'sw.bar.index',
            icon: 'regular-storefront',
            id: 'sw-settings-bar',
            name: 'settings-bar',
            label: 'Bar Setting',
        };

        Shopware.Store.get('settingsItems').addItem(settingsItemToAdd);

        const wrapper = await createWrapper(['system.foo_bar']);
        const shopGroup = wrapper.vm.settingsGroups.shop;

        const barSetting = shopGroup.find((setting) => setting.id === 'sw-settings-bar');
        expect(barSetting).toBeDefined();
    });

    /**
     * @deprecated tag:v6.8.0 - Will be removed
     */
    // @deprecated tag:v6.8.0 - The test will be removed with the settings rename banner.
    it.deprecated('v6.8.0.0')('should load user config for banner on created', async () => {
        await createWrapper();

        expect(Shopware.Service('userConfigService').search).toHaveBeenCalledWith(['settings.hideRenameBanner']);
    });

    /**
     * @deprecated tag:v6.8.0 - Will be removed
     */
    // @deprecated tag:v6.8.0 - The test will be removed with the settings rename banner.
    it.deprecated('v6.8.0.0')('should show banner by default when no config is set', async () => {
        const wrapper = await createWrapper();

        await flushPromises();

        expect(wrapper.vm.hideSettingRenameBanner).toBe(false);
    });

    /**
     * @deprecated tag:v6.8.0 - Will be removed
     */
    // @deprecated tag:v6.8.0 - The test will be removed with the settings rename banner.
    it.deprecated('v6.8.0.0')('should hide banner when config is set to true', async () => {
        Shopware.Service('userConfigService').search.mockResolvedValueOnce({
            data: {
                'settings.hideRenameBanner': {
                    value: true,
                },
            },
        });
        const wrapper = await createWrapper();

        await flushPromises();

        expect(wrapper.vm.hideSettingRenameBanner).toBe(true);
    });

    /**
     * @deprecated tag:v6.8.0 - Will be removed
     */
    // @deprecated tag:v6.8.0 - The test will be removed with the settings rename banner.
    it.deprecated('v6.8.0.0')('should show banner when config is set to false', async () => {
        Shopware.Service('userConfigService').search.mockResolvedValueOnce({
            data: {
                'settings.hideRenameBanner': {
                    value: false,
                },
            },
        });
        const wrapper = await createWrapper();

        await flushPromises();

        expect(wrapper.vm.hideSettingRenameBanner).toBe(false);
    });

    /**
     * @deprecated tag:v6.8.0 - Will be removed
     */
    // @deprecated tag:v6.8.0 - The test will be removed with the settings rename banner.
    it.deprecated('v6.8.0.0')('should toggle banner visibility and save config', async () => {
        Shopware.Service('userConfigService').search.mockResolvedValueOnce({
            data: {
                'settings.hideRenameBanner': {
                    value: true,
                },
            },
        });
        const wrapper = await createWrapper();

        await flushPromises();

        expect(wrapper.vm.hideSettingRenameBanner).toBe(true);

        await wrapper.vm.onCloseSettingRenameBanner();

        expect(wrapper.vm.hideSettingRenameBanner).toBe(true);
        expect(Shopware.Service('userConfigService').upsert).toHaveBeenCalledWith({
            'settings.hideRenameBanner': {
                value: true,
            },
        });
    });

    // @deprecated tag:v6.8.0 - The test will be removed with the settings rename notice.
    it.deprecated('v6.8.0.0')('provides the change notices with the version they can be removed with', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.changeNotices).toEqual([
            {
                key: 'sw-settings.index.textSettingRenameBanner',
                deprecationVersion: 'v6.8.0.0',
            },
        ]);
    });

    /**
     * @deprecated tag:v6.8.0 - Do NOT delete this test, it guards every future change notice.
     * At v6.8: delete the `sw-settings.index.textSettingRenameBanner` entry from
     * `sw-settings-index/index.ts` plus its snippet, then lower this back to a plain `it()`.
     * It is skipped under the flag because it is designed to fail once a notice's version has
     * arrived, and the entry cannot be removed before the major ships.
     */
    it.deprecated('v6.8.0.0')(
        'fails once a change notice reached its deprecation version (delete the entry then)',
        async () => {
            const wrapper = await createWrapper();

            const outdated = wrapper.vm.changeNotices
                .filter((notice) => Shopware.Feature.isActive(notice.deprecationVersion))
                .map((notice) => notice.key);

            expect(
                outdated,
                `Deprecated settings messages not removed! Please remove the outdated notices from 'sw-settings-index/index.ts': ${outdated.join(', ')}`,
            ).toEqual([]);
        },
    );

    describe('search functionality', () => {
        it('should filter items based on search term (term is part of label, case insensitive, white space around)', async () => {
            const wrapper = await createWrapper();
            wrapper.vm.searchQuery = '  uSeR  ';
            await wrapper.vm.$nextTick();

            const settingsGroups = Object.entries(wrapper.vm.settingsGroups);

            expect(settingsGroups).toHaveLength(1);
            const [
                groupName,
                settingsItems,
            ] = settingsGroups[0];
            expect(groupName).toBe('system');
            expect(settingsItems).toStrictEqual([
                {
                    group: 'system',
                    to: 'sw.settings.foo.list',
                    icon: 'regular-user',
                    id: 'sw-settings-foo',
                    name: 'settings-foo',
                    label: "User's Foo & Bar",
                    privilege: 'foo.viewer',
                },
                {
                    group: 'system',
                    to: 'sw.settings.user.list',
                    icon: 'regular-user',
                    id: 'sw-settings-user',
                    name: 'settings-user',
                    label: 'Users & Permissions',
                    privilege: 'user.viewer',
                },
            ]);
        });

        it('should filter items based on search term (label is part of term)', async () => {
            // Item 'Snippets' is expected to be found with search term 'Snippets and more'
            const wrapper = await createWrapper();
            wrapper.vm.searchQuery = 'Snippets and more';
            await wrapper.vm.$nextTick();

            const settingsGroups = Object.entries(wrapper.vm.settingsGroups);

            expect(settingsGroups).toHaveLength(1);
            const [
                groupName,
                settingsItems,
            ] = settingsGroups[0];
            expect(groupName).toBe('shop');
            expect(settingsItems).toStrictEqual([
                {
                    group: 'shop',
                    to: 'sw.settings.snippet.index',
                    icon: 'regular-globe',
                    id: 'sw-settings-snippet',
                    name: 'settings-snippet',
                    label: 'Snippets',
                    privilege: 'snippet.viewer',
                },
            ]);
        });

        it('should show empty state when no settings items are available due to search filtering', async () => {
            const wrapper = await createWrapper();
            wrapper.vm.searchQuery = 'non-existing';
            await wrapper.vm.$nextTick();

            const settingsGroups = Object.entries(wrapper.vm.settingsGroups);

            expect(settingsGroups).toHaveLength(0);

            const emptyState = wrapper.findComponent({ name: 'mt-empty-state' });
            expect(emptyState.exists()).toBe(true);
        });
    });
});
