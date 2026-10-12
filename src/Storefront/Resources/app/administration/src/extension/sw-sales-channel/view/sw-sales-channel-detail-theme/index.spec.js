/**
 * @sw-package discovery
 */
import { shallowMount } from '@vue/test-utils';
import swSalesChannelDetailTheme from './index';

Shopware.Component.register('sw-sales-channel-detail-theme', swSalesChannelDetailTheme);

describe('sw-sales-channel-detail-theme', () => {
    async function createWrapper({
        aclCan = true,
        salesChannel = null,
        themeRepositoryGet = null,
        pendingValues = {},
        salesChannelGet = null,
        getValuesImpl = null,
    } = {}) {
        const component = await Shopware.Component.build('sw-sales-channel-detail-theme');

        const sc = salesChannel || {
            id: 'sales-channel-id',
            extensions: { themes: [{ id: 'theme-id' }] },
        };

        const themeRepository = {
            get: themeRepositoryGet || jest.fn(() => Promise.resolve({ id: 'theme-id' })),
        };

        // by default the live mapping mirrors the prop, so nothing is "pending"
        const salesChannelRepository = {
            get: salesChannelGet || jest.fn(() => Promise.resolve({ extensions: { themes: sc.extensions.themes } })),
        };

        const systemConfigApiService = {
            getValues: getValuesImpl || jest.fn(() => Promise.resolve(pendingValues)),
        };

        return shallowMount(component, {
            props: {
                salesChannel: sc,
            },
            global: {
                stubs: {
                    'sw-card': true,
                    'sw-theme-list-item': true,
                    'sw-theme-modal': true,
                    'sw-button': true,
                    'mt-loader': true,
                },
                directives: {
                    tooltip: {},
                },
                provide: {
                    repositoryFactory: {
                        create: (name) => (name === 'sales_channel' ? salesChannelRepository : themeRepository),
                    },
                    themeService: {},
                    systemConfigApiService,
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

    it('shows the pending theme while a deferred switch compiles in the background', async () => {
        const wrapper = await createWrapper({
            salesChannel: { id: 'sc', extensions: { themes: [{ id: 'live-id' }] } },
            pendingValues: { 'storefront.pendingThemeAssignment': 'pending-id' },
            salesChannelGet: jest.fn(() => Promise.resolve({ extensions: { themes: [{ id: 'live-id' }] } })),
            themeRepositoryGet: jest.fn((id) => Promise.resolve({ id, name: id })),
        });

        await flushPromises();

        expect(wrapper.vm.pendingTheme).toEqual({ id: 'pending-id', name: 'pending-id' });
        // the live theme stays the headline truth, with a tooltip explaining the pending switch
        expect(wrapper.vm.theme.id).toBe('live-id');
        expect(wrapper.vm.pendingTooltip).toBeTruthy();
    });

    it('shows the pending theme even when no theme is live yet', async () => {
        const wrapper = await createWrapper({
            salesChannel: { id: 'sc', extensions: { themes: [] } },
            pendingValues: { 'storefront.pendingThemeAssignment': 'pending-id' },
            salesChannelGet: jest.fn(() => Promise.resolve({ extensions: { themes: [] } })),
            themeRepositoryGet: jest.fn((id) => Promise.resolve({ id, name: 'Pending Theme' })),
        });

        await flushPromises();

        expect(wrapper.vm.theme).toBeNull();
        expect(wrapper.vm.pendingTheme).toEqual({ id: 'pending-id', name: 'Pending Theme' });
    });

    it('shows no pending banner when there is no in-flight switch', async () => {
        const wrapper = await createWrapper({
            salesChannel: { id: 'sc', extensions: { themes: [{ id: 'live-id' }] } },
            // pending equals the live theme -> nothing in flight
            pendingValues: { 'storefront.pendingThemeAssignment': 'live-id' },
            salesChannelGet: jest.fn(() => Promise.resolve({ extensions: { themes: [{ id: 'live-id' }] } })),
        });

        await flushPromises();

        expect(wrapper.vm.pendingTheme).toBeNull();
    });

    it('clears the banner and updates the displayed theme once the switch is applied, without touching the draft', async () => {
        jest.useFakeTimers();

        const salesChannelGet = jest
            .fn()
            .mockResolvedValueOnce({ extensions: { themes: [{ id: 'live-id' }] } }) // still the old mapping
            .mockResolvedValue({ extensions: { themes: [{ id: 'pending-id' }] } }); // flipped by the worker

        const salesChannel = { id: 'sc', extensions: { themes: [{ id: 'live-id' }] } };
        const wrapper = await createWrapper({
            salesChannel,
            pendingValues: { 'storefront.pendingThemeAssignment': 'pending-id' },
            salesChannelGet,
            themeRepositoryGet: jest.fn((id) => Promise.resolve({ id, name: id })),
        });

        await flushPromises();
        expect(wrapper.vm.pendingTheme.id).toBe('pending-id');

        jest.advanceTimersByTime(10000);
        await flushPromises();

        expect(wrapper.vm.pendingTheme).toBeNull();
        expect(wrapper.vm.theme.id).toBe('pending-id');
        // regression guard: the deferred assignment must never be written into the entity draft
        expect(salesChannel.extensions.themes).toEqual([{ id: 'live-id' }]);

        jest.useRealTimers();
    });

    it('re-checks the pending assignment when the sales channel is reloaded (e.g. after save)', async () => {
        // after a deferred switch the assigned theme is unchanged, so only the sales channel
        // prop is replaced on reload - the banner must appear then, not only on a full refresh
        const getValues = jest
            .fn()
            .mockResolvedValueOnce({}) // on creation: nothing pending
            .mockResolvedValue({ 'storefront.pendingThemeAssignment': 'pending-id' }); // after the save reload

        const wrapper = await createWrapper({
            salesChannel: { id: 'sc', extensions: { themes: [{ id: 'live-id' }] } },
            getValuesImpl: getValues,
            salesChannelGet: jest.fn(() => Promise.resolve({ extensions: { themes: [{ id: 'live-id' }] } })),
            themeRepositoryGet: jest.fn((id) => Promise.resolve({ id, name: id })),
        });

        await flushPromises();
        expect(wrapper.vm.pendingTheme).toBeNull();

        // the base page replaces the sales channel entity on reload after save
        await wrapper.setProps({ salesChannel: { id: 'sc', extensions: { themes: [{ id: 'live-id' }] } } });
        await flushPromises();

        expect(wrapper.vm.pendingTheme).toEqual({ id: 'pending-id', name: 'pending-id' });
    });

    it('does not resume polling when unmounted while a check is in flight', async () => {
        let resolvePending;
        const getValues = jest.fn(
            () =>
                new Promise((resolve) => {
                    resolvePending = resolve;
                }),
        );

        const wrapper = await createWrapper({
            salesChannel: { id: 'sc', extensions: { themes: [{ id: 'live-id' }] } },
            getValuesImpl: getValues,
            salesChannelGet: jest.fn(() => Promise.resolve({ extensions: { themes: [{ id: 'live-id' }] } })),
            themeRepositoryGet: jest.fn((id) => Promise.resolve({ id, name: id })),
        });

        // the check started in created() is awaiting getValues; tear the component down first
        wrapper.unmount();

        // the in-flight read resolves only after unmount and must be discarded
        resolvePending({ 'storefront.pendingThemeAssignment': 'pending-id' });
        await flushPromises();

        expect(wrapper.vm.pendingTheme).toBeNull();
        expect(wrapper.vm.pendingCheckTimeoutId).toBeNull();
    });

    it('discards a stale in-flight check after the sales channel changes', async () => {
        let resolveFirst;
        const getValues = jest
            .fn()
            .mockImplementationOnce(
                () =>
                    new Promise((resolve) => {
                        resolveFirst = resolve;
                    }),
            )
            .mockResolvedValue({ 'storefront.pendingThemeAssignment': 'new-pending' });

        const wrapper = await createWrapper({
            salesChannel: { id: 'sc', extensions: { themes: [{ id: 'live-id' }] } },
            getValuesImpl: getValues,
            salesChannelGet: jest.fn(() => Promise.resolve({ extensions: { themes: [{ id: 'live-id' }] } })),
            themeRepositoryGet: jest.fn((id) => Promise.resolve({ id, name: id })),
        });

        // a new sales channel replaces the prop while the first check is still awaiting
        await wrapper.setProps({ salesChannel: { id: 'sc2', extensions: { themes: [{ id: 'live-id' }] } } });
        await flushPromises();

        // the second check resolved with the newer pending theme
        expect(wrapper.vm.pendingTheme.id).toBe('new-pending');

        // the first (stale) check now resolves with a different theme and must be ignored
        resolveFirst({ 'storefront.pendingThemeAssignment': 'stale-pending' });
        await flushPromises();

        expect(wrapper.vm.pendingTheme.id).toBe('new-pending');

        wrapper.unmount();
    });

    it('stops polling when the component is destroyed', async () => {
        jest.useFakeTimers();

        const wrapper = await createWrapper({
            salesChannel: { id: 'sc', extensions: { themes: [{ id: 'live-id' }] } },
            pendingValues: { 'storefront.pendingThemeAssignment': 'pending-id' },
            salesChannelGet: jest.fn(() => Promise.resolve({ extensions: { themes: [{ id: 'live-id' }] } })),
            themeRepositoryGet: jest.fn((id) => Promise.resolve({ id, name: id })),
        });

        await flushPromises();
        expect(wrapper.vm.pendingCheckTimeoutId).not.toBeNull();

        wrapper.unmount();

        expect(wrapper.vm.pendingCheckTimeoutId).toBeNull();

        jest.useRealTimers();
    });
});
