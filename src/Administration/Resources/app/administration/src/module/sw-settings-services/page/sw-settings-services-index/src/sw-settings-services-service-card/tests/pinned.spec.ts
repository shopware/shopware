import {
    clickDeactivateModalButton,
    clickMenuItem,
    closePermissionsModal,
    createService,
    getFooterText,
    getMenuItems,
    getPermissionsModal,
    getShownIcon,
    getShownStatus,
    isDeactivateModalOpen,
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

describe('sw-settings-services-service-card', () => {
    afterEach(unmountServiceCards);

    it.each([
        ['pending_permissions', 'orange', 'sw-settings-services.service-card.status-awaiting-permissions'],
        ['inactive', 'red', 'sw-settings-services.service-card.status-inactive'],
        ['something-we-do-not-know', 'red', 'sw-settings-services.service-card.status-inactive'],
    ])('shows the state %s as a %s status', async (state, color, label) => {
        // ACT
        const wrapper = await mountServiceCard(createService({ state: state as never }));

        // ASSERT
        expect(getShownStatus(wrapper)).toEqual({ color, label });
    });

    it('shows the icon of the service as a base64 png', async () => {
        // ACT
        const wrapper = await mountServiceCard(createService({ icon: 'service-icon' }));

        // ASSERT
        expect(getShownIcon(wrapper)).toBe('data:image/png;base64, service-icon');
    });

    it('shows a placeholder icon for a service without an icon', async () => {
        // ACT
        const wrapper = await mountServiceCard(createService({ icon: null as never }));

        // ASSERT
        expect(getShownIcon(wrapper)).toBe(
            'administration/administration/static/img/services/extension-icon-placeholder.svg',
        );
    });

    it('shows the update date and the version without its build hash', async () => {
        // ACT
        const wrapper = await mountServiceCard(createService());

        // ASSERT
        expect(getFooterText(wrapper)).toContain(
            'sw-settings-services.service-card.updated-at-label {"updatedAt":"08/07/2025"}',
        );
        expect(getFooterText(wrapper)).toContain('sw-settings-services.service-card.version {"version":"1.0.0"}');
    });

    it('offers deactivation and the permissions for an active service', async () => {
        // ARRANGE
        const wrapper = await mountServiceCard(createService({ active: true }));

        // ACT
        await openMenu(wrapper);

        // ASSERT
        expect(getMenuItems(wrapper)).toEqual([
            { label: 'sw-settings-services.general.deactivate', disabled: false },
            { label: 'sw-settings-services.service-card.permissions', disabled: false },
        ]);
    });

    it('offers activation and the permissions for an inactive service', async () => {
        // ARRANGE
        const wrapper = await mountServiceCard(createService({ active: false }));

        // ACT
        await openMenu(wrapper);

        // ASSERT
        expect(getMenuItems(wrapper)).toEqual([
            { label: 'sw-settings-services.general.activate', disabled: false },
            { label: 'sw-settings-services.service-card.permissions', disabled: false },
        ]);
    });

    it('offers no activation for an inactive service whose state may not be changed', async () => {
        // ARRANGE
        const wrapper = await mountServiceCard(createService({ active: false, state_change_permitted: false }));

        // ACT
        await openMenu(wrapper);

        // ASSERT
        expect(getMenuItems(wrapper).map((item) => item.label)).toEqual([
            'sw-settings-services.service-card.cannot-remove-or-deactivate',
            'sw-settings-services.service-card.permissions',
        ]);
    });

    it('opens the deactivate modal without deactivating the service', async () => {
        // ARRANGE
        const wrapper = await mountServiceCard(createService({ active: true }));
        await openMenu(wrapper);

        // ACT
        await clickMenuItem(wrapper, 'sw-settings-services.general.deactivate');

        // ASSERT
        expect(isDeactivateModalOpen()).toBe(true);
        expect(servicesService.deactivateService).not.toHaveBeenCalled();
    });

    it('closes the deactivate modal on cancel and keeps the service active', async () => {
        // ARRANGE
        const wrapper = await mountServiceCard(createService({ active: true }));
        await openMenu(wrapper);
        await clickMenuItem(wrapper, 'sw-settings-services.general.deactivate');

        // ACT
        await clickDeactivateModalButton(wrapper, 'cancel');

        // ASSERT
        expect(isDeactivateModalOpen()).toBe(false);
        expect(servicesService.deactivateService).not.toHaveBeenCalled();
    });

    it('shows an error and does not reload when deactivating fails', async () => {
        // ARRANGE
        servicesService.deactivateService.mockRejectedValue(new Error('Service unavailable'));
        const notifications = recordNotifications();
        const wrapper = await mountServiceCard(createService({ active: true }));
        await openMenu(wrapper);
        await clickMenuItem(wrapper, 'sw-settings-services.general.deactivate');

        // ACT
        await clickDeactivateModalButton(wrapper, 'deactivate');

        // ASSERT
        expect(notifications).toHaveBeenCalledWith({ variant: 'critical', message: 'Service unavailable' });
        expect(pageReload).not.toHaveBeenCalled();
    });

    it('loads the permissions only once', async () => {
        // ARRANGE
        servicesService.getCategorizedPermissions.mockResolvedValue({ permissions: {} });
        const wrapper = await mountServiceCard(createService());
        await openMenu(wrapper);
        await clickMenuItem(wrapper, 'sw-settings-services.service-card.permissions');
        await closePermissionsModal(wrapper);

        // ACT
        await openMenu(wrapper);
        await clickMenuItem(wrapper, 'sw-settings-services.service-card.permissions');

        // ASSERT
        expect(servicesService.getCategorizedPermissions).toHaveBeenCalledTimes(1);
        expect(getPermissionsModal(wrapper).exists()).toBe(true);
    });

    it('closes the permissions modal when it asks to', async () => {
        // ARRANGE
        servicesService.getCategorizedPermissions.mockResolvedValue({ permissions: {} });
        const wrapper = await mountServiceCard(createService());
        await openMenu(wrapper);
        await clickMenuItem(wrapper, 'sw-settings-services.service-card.permissions');

        // ACT
        await closePermissionsModal(wrapper);

        // ASSERT
        expect(getPermissionsModal(wrapper).exists()).toBe(false);
    });

    it('shows an error and no permissions modal when the permissions cannot be loaded', async () => {
        // ARRANGE
        servicesService.getCategorizedPermissions.mockRejectedValue(new Error('Service unavailable'));
        const notifications = recordNotifications();
        const wrapper = await mountServiceCard(createService());
        await openMenu(wrapper);

        // ACT
        await clickMenuItem(wrapper, 'sw-settings-services.service-card.permissions');

        // ASSERT
        expect(notifications).toHaveBeenCalledWith({ variant: 'critical', message: 'Service unavailable' });
        expect(getPermissionsModal(wrapper).exists()).toBe(false);
    });
});
