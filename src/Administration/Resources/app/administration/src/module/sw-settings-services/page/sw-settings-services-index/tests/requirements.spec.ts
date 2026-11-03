import {
    arrangeInstalledServices,
    clickActivateServices,
    countServiceCards,
    getBanner,
    mountServicesPage,
    servicesService,
} from './services-page.harness';

/**
 * @sw-package framework
 */

/*
 * The services settings page lists the Shopware services installed in the shop, one card per service.
 * When the merchant deactivated the services, it offers to activate them all again.
 * The services and their context come from the shopwareServicesService, each card is the nested sw-settings-services-service-card module.
 */
describe('sw-settings-services-index', () => {
    beforeEach(arrangeInstalledServices);

    /**
     * @requirement A merchant sees every installed service, so they know what runs in their shop.
     */
    it('shows a card for every installed service', async () => {
        // ACT
        const page = await mountServicesPage();

        // ASSERT
        expect(countServiceCards(page)).toBe(2);
    });

    /**
     * @requirement A merchant who deactivated the services can activate them all again from the page.
     */
    it('activates all services when the merchant asks for it', async () => {
        // ARRANGE
        servicesService.getInstalledServices.mockResolvedValueOnce([]);
        servicesService.getServicesContext.mockResolvedValueOnce({ disabled: true, permissionsConsent: null });
        const page = await mountServicesPage();

        // ACT
        await clickActivateServices(page);

        // ASSERT
        expect(servicesService.enableAllServices).toHaveBeenCalled();
        expect(page.find('.sw-settings-services-index__installing-card').exists()).toBe(true);
    });

    /**
     * @requirement A merchant sees when the services could not be loaded, instead of an empty page.
     */
    it('shows an error when the services cannot be loaded', async () => {
        // ARRANGE
        servicesService.getInstalledServices.mockRejectedValueOnce(new Error('failed loading services'));

        // ACT
        const page = await mountServicesPage();

        // ASSERT
        const banner = getBanner(page);
        expect(banner.variant).toBe('critical');
        expect(banner.text).toContain('failed loading services');
    });
});
