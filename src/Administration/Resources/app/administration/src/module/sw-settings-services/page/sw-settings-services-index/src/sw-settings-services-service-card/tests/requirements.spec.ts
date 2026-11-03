import {
    clickDeactivateModalButton,
    clickMenuItem,
    createService,
    getFooterText,
    getMenuItems,
    getPermissionsModal,
    getShownStatus,
    mountServiceCard,
    openMenu,
    pageReload,
    recordNotifications,
    servicesService,
    unmountServiceCards,
} from './service-card.harness';

/**
 * @sw-package framework
 */

/*
 * The services settings page shows one card per Shopware service.
 * The card shows what the service is and whether it runs, and its menu lets a merchant activate or deactivate it and read its permissions.
 * The services come from the services page, the changes go to the shopwareServicesService.
 */
describe('sw-settings-services-service-card', () => {
    afterEach(unmountServiceCards);

    /**
     * @requirement A merchant sees each service with its label, description and whether it is active.
     */
    it('shows a service with its label, description and status', async () => {
        // ACT
        const wrapper = await mountServiceCard(createService({ state: 'active' }));

        // ASSERT
        expect(wrapper.text()).toContain('service-label');
        expect(wrapper.text()).toContain('service-description');
        expect(getShownStatus(wrapper)).toEqual({
            color: 'green',
            label: 'sw-settings-services.service-card.status-active',
        });
    });

    /**
     * @requirement A merchant sees which version of a service runs and when it was last updated.
     * Services update themselves, so the card tells what changed recently.
     */
    it('shows the version and the date of the last update', async () => {
        // ACT
        const wrapper = await mountServiceCard(createService());

        // ASSERT
        expect(getFooterText(wrapper)).toContain('sw-settings-services.service-card.updated-at-label');
        expect(getFooterText(wrapper)).toContain('sw-settings-services.service-card.version');
    });

    /**
     * @requirement A merchant can deactivate an active service after confirming it, and the page reloads.
     * Deactivating removes what the service adds to the Administration, so it asks first and reloads to show the result.
     */
    it('deactivates a service after the merchant confirms it', async () => {
        // ARRANGE
        servicesService.deactivateService.mockResolvedValue(undefined);
        const wrapper = await mountServiceCard(createService({ active: true }));
        await openMenu(wrapper);
        await clickMenuItem(wrapper, 'sw-settings-services.general.deactivate');

        // ACT
        await clickDeactivateModalButton(wrapper, 'deactivate');

        // ASSERT
        expect(servicesService.deactivateService).toHaveBeenCalledWith('service-name');
        expect(pageReload).toHaveBeenCalledTimes(1);
    });

    /**
     * @requirement A merchant can activate an inactive service, and the page reloads.
     */
    it('activates an inactive service', async () => {
        // ARRANGE
        servicesService.activateService.mockResolvedValue(undefined);
        const wrapper = await mountServiceCard(createService({ active: false, state: 'inactive' }));
        await openMenu(wrapper);

        // ACT
        await clickMenuItem(wrapper, 'sw-settings-services.general.activate');

        // ASSERT
        expect(servicesService.activateService).toHaveBeenCalledWith('service-name');
        expect(pageReload).toHaveBeenCalledTimes(1);
    });

    /**
     * @requirement A merchant can read which permissions a service has and which domains it talks to.
     * A merchant decides whether to trust a service with the shop's data.
     */
    it('shows the permissions of a service', async () => {
        // ARRANGE
        const permissions = { order: [{ extensions: [], entity: 'order', operation: 'read' }] };
        servicesService.getCategorizedPermissions.mockResolvedValue({ permissions });
        const wrapper = await mountServiceCard(createService({ domains: ['url-to-app-server'] }));
        await openMenu(wrapper);

        // ACT
        await clickMenuItem(wrapper, 'sw-settings-services.service-card.permissions');

        // ASSERT
        expect(getPermissionsModal(wrapper).props()).toEqual({
            extensionLabel: 'service-label',
            permissions,
            domains: ['url-to-app-server'],
        });
    });

    /**
     * @requirement A merchant cannot activate or deactivate a service whose state is managed elsewhere, and the menu says so.
     * Some services depend on other things, such as a Shopware account, and only follow those.
     */
    it('offers no activation or deactivation for a service whose state may not be changed', async () => {
        // ARRANGE
        const wrapper = await mountServiceCard(createService({ state_change_permitted: false }));

        // ACT
        await openMenu(wrapper);

        // ASSERT
        expect(getMenuItems(wrapper)).toEqual([
            { label: 'sw-settings-services.service-card.cannot-remove-or-deactivate', disabled: true },
            { label: 'sw-settings-services.service-card.permissions', disabled: false },
        ]);
    });

    /**
     * @requirement A merchant sees an error when a service cannot be activated, and the page stays as it is.
     */
    it('shows an error when activating a service fails', async () => {
        // ARRANGE
        servicesService.activateService.mockRejectedValue(new Error('Service unavailable'));
        const notifications = recordNotifications();
        const wrapper = await mountServiceCard(createService({ active: false, state: 'inactive' }));
        await openMenu(wrapper);

        // ACT
        await clickMenuItem(wrapper, 'sw-settings-services.general.activate');

        // ASSERT
        expect(notifications).toHaveBeenCalledWith({ variant: 'critical', message: 'Service unavailable' });
        expect(pageReload).not.toHaveBeenCalled();
    });
});
