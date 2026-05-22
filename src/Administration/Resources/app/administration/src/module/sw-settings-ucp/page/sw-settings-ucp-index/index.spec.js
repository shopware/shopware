/**
 * @sw-package fundamentals@framework
 */

import swSettingsUcpIndex from './index';

function createComponent({ listSalesChannels = jest.fn(() => Promise.resolve({ items: [] })) } = {}) {
    const component = {
        ...swSettingsUcpIndex.data(),
        ucpAdminService: { listSalesChannels },
        createNotificationError: jest.fn(),
        $router: { push: jest.fn() },
        $t: (key) => key,
    };

    Object.entries(swSettingsUcpIndex.methods).forEach(
        ([
            name,
            fn,
        ]) => {
            component[name] = fn.bind(component);
        },
    );

    return component;
}

describe('module/sw-settings-ucp/page/sw-settings-ucp-index', () => {
    it('exposes the expected reactive data shape', () => {
        const data = swSettingsUcpIndex.data();
        expect(data).toEqual({ isLoading: true, items: [] });
    });

    it('declares ucpAdminService injection and the notification mixin', () => {
        expect(swSettingsUcpIndex.inject).toEqual(['ucpAdminService']);
        expect(swSettingsUcpIndex.mixins).toHaveLength(1);
    });

    it('loadItems populates items from the api response', async () => {
        const items = [
            {
                salesChannelId: 'sc-1',
                active: true,
                configured: true,
                enabledCapabilities: [
                    'catalog',
                    'cart',
                ],
            },
            { salesChannelId: 'sc-2', active: false, configured: false, enabledCapabilities: [] },
        ];
        const listSalesChannels = jest.fn(() => Promise.resolve({ items }));
        const component = createComponent({ listSalesChannels });

        await component.loadItems();

        expect(listSalesChannels).toHaveBeenCalledTimes(1);
        expect(component.items).toHaveLength(2);
        expect(component.isLoading).toBe(false);
    });

    it('loadItems shows a notification on error and resets loading state', async () => {
        const listSalesChannels = jest.fn(() => Promise.reject(new Error('boom')));
        const component = createComponent({ listSalesChannels });

        await component.loadItems();

        expect(component.createNotificationError).toHaveBeenCalledWith({ message: 'boom' });
        expect(component.isLoading).toBe(false);
    });

    it('capabilityCount returns the length of enabledCapabilities or 0', () => {
        const component = createComponent();

        expect(
            component.capabilityCount({
                enabledCapabilities: [
                    'a',
                    'b',
                    'c',
                ],
            }),
        ).toBe(3);
        expect(component.capabilityCount({ enabledCapabilities: [] })).toBe(0);
        expect(component.capabilityCount({})).toBe(0);
    });

    it('statusLabel maps configured/active state to the correct snippet key', () => {
        const component = createComponent();

        expect(component.statusLabel({ configured: false })).toBe('sw-settings-ucp.status.notConfigured');
        expect(component.statusLabel({ configured: true, active: true })).toBe('sw-settings-ucp.status.active');
        expect(component.statusLabel({ configured: true, active: false })).toBe('sw-settings-ucp.status.inactive');
    });

    it('statusVariant uses the same three-state machine as statusLabel (never green when not configured)', () => {
        const component = createComponent();

        expect(component.statusVariant({ configured: true, active: true })).toBe('positive');
        expect(component.statusVariant({ configured: true, active: false })).toBe('neutral');
        expect(component.statusVariant({ configured: false, active: false })).toBe('neutral');
        // Regression: a row that is "not configured" must never paint a green
        // badge, even if some upstream change leaves `active: true` on it —
        // the badge text and badge colour must agree.
        expect(component.statusVariant({ configured: false, active: true })).toBe('neutral');
    });

    it.each([
        // [configured, active, expectedVariant,        expectedLabel]
        [
            false,
            false,
            'neutral',
            'sw-settings-ucp.status.notConfigured',
        ],
        [
            false,
            true,
            'neutral',
            'sw-settings-ucp.status.notConfigured',
        ],
        [
            true,
            false,
            'neutral',
            'sw-settings-ucp.status.inactive',
        ],
        [
            true,
            true,
            'positive',
            'sw-settings-ucp.status.active',
        ],
    ])(
        'state(configured=%s, active=%s) renders variant=%s + label=%s — variant and label always agree',
        (configured, active, expectedVariant, expectedLabel) => {
            const component = createComponent();
            const item = { configured, active };

            expect(component.statusVariant(item)).toBe(expectedVariant);
            expect(component.statusLabel(item)).toBe(expectedLabel);
        },
    );

    it('editItem navigates to the detail route with the sales-channel id', () => {
        const component = createComponent();

        component.editItem({ salesChannelId: 'sc-1' });

        expect(component.$router.push).toHaveBeenCalledWith({
            name: 'sw.settings.ucp.detail',
            params: { salesChannelId: 'sc-1' },
        });
    });
});
