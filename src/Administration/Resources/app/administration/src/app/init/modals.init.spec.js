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

    it('should handle incoming uiModalUpdate requests and update the modal', async () => {
        // Open modal
        await ui.modal.open({
            title: 'Initial title',
            locationId: 'update-location-id',
            variant: 'small',
        });

        expect(Shopware.Store.get('modals').modals[0].title).toBe('Initial title');

        // Update modal
        await ui.modal.update({
            locationId: 'update-location-id',
            title: 'Updated title',
            variant: 'large',
        });

        expect(Shopware.Store.get('modals').modals[0].title).toBe('Updated title');
        expect(Shopware.Store.get('modals').modals[0].variant).toBe('large');
    });

    it('should update the buttons when uiModalUpdate is called', async () => {
        await ui.modal.open({
            title: 'Button Modal',
            locationId: 'button-modal-id',
            buttons: [{ label: 'Old Button', method: () => {} }],
        });

        expect(Shopware.Store.get('modals').modals[0].buttons).toHaveLength(1);
        expect(Shopware.Store.get('modals').modals[0].buttons[0].label).toBe('Old Button');

        await ui.modal.update({
            locationId: 'button-modal-id',
            buttons: [
                { label: 'New Button', method: () => {} },
                { label: 'Another Button', method: () => {} },
            ],
        });

        expect(Shopware.Store.get('modals').modals[0].buttons).toHaveLength(2);
        expect(Shopware.Store.get('modals').modals[0].buttons[0].label).toBe('New Button');
        expect(Shopware.Store.get('modals').modals[0].buttons[1].label).toBe('Another Button');
    });

    it('should tell the opener that close results are supported', async () => {
        const modal = await ui.modal.open({ title: 'Result modal', locationId: 'result-modal' });

        expect(modal.supportsResult).toBe(true);
    });

    it('should pass the result of uiModalClose to the opener', async () => {
        const modal = await ui.modal.open({ title: 'Result modal', locationId: 'result-modal' });
        const onClose = jest.fn();
        modal.onClose(onClose);

        await ui.modal.close({ locationId: 'result-modal', result: { saved: true } });
        await flushPromises();

        expect(onClose).toHaveBeenCalledWith({ reason: 'closed', data: { saved: true } });
        await expect(modal.closed).resolves.toEqual({ reason: 'closed', data: { saved: true } });
    });

    it('should tell the opener when the user closes the modal', async () => {
        const modal = await ui.modal.open({ title: 'Result modal', locationId: 'result-modal' });

        Shopware.Store.get('modals').dismissModal(Shopware.Store.get('modals').modals[0]);

        await expect(modal.closed).resolves.toEqual({ reason: 'dismissed' });
    });

    describe('uiModalGetProps', () => {
        const extensionOrigin = 'http://localhost';

        // jsdom reports an empty origin for postMessage; give the messages the origin a browser would.
        const addOrigin = (event) => {
            if (event.origin === '') {
                event.stopImmediatePropagation();
                window.dispatchEvent(new MessageEvent('message', { data: event.data, origin: extensionOrigin }));
            }
        };

        beforeEach(() => {
            window.addEventListener('message', addOrigin, true);
            Shopware.Store.get('extensions').extensionsState = {};
            Shopware.Store.get('extensions').addExtension({
                name: 'jestapp',
                baseUrl: `${extensionOrigin}/app`,
                permissions: [],
                version: '1.0.0',
                type: 'app',
                integrationId: '123',
                active: true,
            });
            window.history.replaceState({}, '', '/?location-id=props-modal&sw-modal-props=1');
        });

        afterEach(() => {
            window.removeEventListener('message', addOrigin, true);
            window.history.replaceState({}, '', '/');
        });

        it('should return the props of the modal to its location', async () => {
            await ui.modal.open({ title: 'Props modal', locationId: 'props-modal', props: { tab: 'tone' } });

            await expect(ui.modal.getProps()).resolves.toEqual({ tab: 'tone' });
        });

        it('should return null when the modal of the location is not open', async () => {
            await expect(ui.modal.getProps()).resolves.toBeNull();
        });
    });
});
