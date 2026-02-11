/**
 * @sw-package discovery
 */
import { shallowMount } from '@vue/test-utils';
import './index';

describe('sw-sales-channel-detail-theme', () => {
    async function createWrapper({ aclCan = true, salesChannel = null, themeRepositoryGet = null } = {}) {
        const component = await Shopware.Component.build('sw-sales-channel-detail-theme');

        const themeRepository = {
            get: themeRepositoryGet || jest.fn(() => Promise.resolve({ id: 'theme-id' })),
        };

        return shallowMount(component, {
            props: {
                salesChannel: salesChannel || {
                    id: 'sales-channel-id',
                    extensions: { themes: [{ id: 'theme-id' }] },
                },
            },
            global: {
                stubs: {
                    'sw-card': true,
                    'sw-theme-list-item': true,
                    'sw-theme-modal': true,
                    'sw-button': true,
                },
                provide: {
                    repositoryFactory: {
                        create: () => themeRepository,
                    },
                    themeService: {},
                    acl: {
                        can: jest.fn(() => aclCan),
                    },
                },
                mocks: {
                    $router: { push: jest.fn() },
                },
            },
        });
    }

    it('loads theme from sales channel on creation', async () => {
        const theme = { id: 'theme-id', name: 'Theme' };
        const wrapper = await createWrapper({
            themeRepositoryGet: jest.fn(() => Promise.resolve(theme)),
        });

        await flushPromises();

        expect(wrapper.vm.theme).toEqual(theme);
    });

    it('skips theme load when id is null', async () => {
        const themeRepositoryGet = jest.fn(() => Promise.resolve({ id: 'theme-id' }));
        const wrapper = await createWrapper({
            themeRepositoryGet,
            salesChannel: {
                id: 'sales-channel-id',
                extensions: { themes: [] },
            },
        });
        themeRepositoryGet.mockClear();

        await wrapper.vm.getTheme(null);

        expect(themeRepositoryGet).not.toHaveBeenCalled();
    });

    it('opens theme selection modal when ACL allows', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.openThemeModal();

        expect(wrapper.vm.showThemeSelectionModal).toBe(true);
    });

    it('does not open theme selection modal when ACL blocks', async () => {
        const wrapper = await createWrapper({ aclCan: false });

        wrapper.vm.openThemeModal();

        expect(wrapper.vm.showThemeSelectionModal).toBe(false);
    });

    it('navigates to theme manager detail when theme exists', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.theme = { id: 'theme-id' };

        wrapper.vm.openInThemeManager();

        expect(wrapper.vm.$router.push).toHaveBeenCalledWith({
            name: 'sw.theme.manager.detail',
            params: { id: 'theme-id' },
        });
    });

    it('navigates to theme manager list when theme is missing', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.theme = null;

        wrapper.vm.openInThemeManager();

        expect(wrapper.vm.$router.push).toHaveBeenCalledWith({
            name: 'sw.theme.manager.index',
        });
    });

    it('changes theme and updates sales channel extension', async () => {
        const theme = { id: 'theme-id', name: 'Theme' };
        const wrapper = await createWrapper({
            themeRepositoryGet: jest.fn(() => Promise.resolve(theme)),
        });

        await wrapper.vm.onChangeTheme('theme-id');
        await flushPromises();

        expect(wrapper.vm.showThemeSelectionModal).toBe(false);
        expect(wrapper.vm.salesChannel.extensions.themes[0]).toEqual(theme);
    });
});
