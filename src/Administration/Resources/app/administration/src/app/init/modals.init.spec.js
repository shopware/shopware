/**
 * @sw-package framework
 */
import initializeModal from 'src/app/init/modals.init';
import { ui } from '@shopware-ag/meteor-admin-sdk';

describe('src/app/init/modals.init.ts', () => {
    beforeAll(() => {
        initializeModal();
    });

    beforeEach(() => {
        Shopware.Store.get('modals').modals = [];

        Shopware.Store.get('extensions').extensionsState = {};
        Shopware.Store.get('extensions').addExtension({
            name: 'jestapp',
            baseUrl: '',
            permissions: [],
            version: '1.0.0',
            type: 'app',
            integrationId: '123',
            active: true,
        });
    });

    it('should handle incoming uiModalOpen requests', async () => {
        await ui.modal.open({
            title: 'Your modal title',
            locationId: 'your-location-id',
            variant: 'large',
            showHeader: true,
            showFooter: false,
            closable: true,
            buttons: [
                {
                    label: 'Dispatch notification',
                    method: () => {
                        // method content
                    },
                },
                {
                    label: 'Close modal',
                    variant: 'primary',
                    method: () => {
                        ui.modal.close({
                            locationId: 'your-location-id',
                        });
                    },
                },
            ],
        });

        expect(Shopware.Store.get('modals').modals).toHaveLength(1);
    });

    it('should handle incoming uiModalClose requests', async () => {
        await ui.modal.open({
            title: 'Your modal title',
            locationId: 'your-location-id',
            variant: 'large',
            showHeader: true,
            showFooter: false,
            closable: true,
            buttons: [
                {
                    label: 'Dispatch notification',
                    method: () => {
                        // method content
                    },
                },
                {
                    label: 'Close modal',
                    variant: 'primary',
                    method: () => {
                        ui.modal.close({
                            locationId: 'your-location-id',
                        });
                    },
                },
            ],
        });

        expect(Shopware.Store.get('modals').modals).toHaveLength(1);

        await ui.modal.close({
            locationId: 'your-location-id',
        });

        expect(Shopware.Store.get('modals').modals).toHaveLength(0);
    });

    it('should not handle requests when extension is not valid', async () => {
        Shopware.Store.get('extensions').extensionsState = {};

        await expect(async () => {
            await ui.modal.open({
                title: 'Your modal title',
                locationId: 'your-location-id',
                variant: 'large',
                showHeader: true,
                showFooter: false,
                closable: true,
                buttons: [
                    {
                        label: 'Dispatch notification',
                        method: () => {
                            // method content
                        },
                    },
                    {
                        label: 'Close modal',
                        variant: 'primary',
                        method: () => {
                            ui.modal.close({
                                locationId: 'your-location-id',
                            });
                        },
                    },
                ],
            });
        }).rejects.toThrow(new Error('Extension with the origin "" not found.'));

        expect(Shopware.Store.get('extensionSdkModules').modules).toHaveLength(0);
    });
});
