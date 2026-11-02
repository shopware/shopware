import { mount } from '@vue/test-utils';
import SwStatus from 'src/app/component/utils/sw-status';
import {
    MtModalAction,
    MtModalRoot,
    MtModal,
    MtModalTrigger,
    MtPopover,
    MtPopoverItem,
    MtButton,
} from '@shopware-ag/meteor-component-library';
import SwSettingsServicesServiceCard from './index';
import SwColorBadge from '../../../../app/component/utils/sw-color-badge';

const createService = (overrides = {}) => ({
    id: 'service-id',
    active: true,
    name: 'service-name',
    label: 'service-label',
    icon: 'service-icon',
    description: 'service-description',
    updated_at: '2025-07-08 11:21:44.819',
    version: '1.0.0-b63f0ad27d1ee5a22871637a2ffcdc80',
    requested_privileges: [],
    privileges: [],
    state: 'active',
    domains: [],
    requirements: [],
    state_change_permitted: true,
    ...overrides,
});

describe('src/module/sw-settings-services/component/sw-settings-services-service-card.ts', () => {
    beforeAll(() => {
        Shopware.Service().register('shopwareServicesService', () => ({
            activateService: jest.fn(),
            deactivateService: jest.fn(),
            getCategorizedPermissions: jest.fn(),
        }));
    });

    it.each([
        [
            'active',
            'green',
            'active',
        ],
        [
            'pending_permissions',
            'orange',
            'awaiting-permissions',
        ],
        [
            'inactive',
            'red',
            'inactive',
        ],
        [
            'something-we-do-not-know',
            'red',
            'inactive',
        ],
    ])('displays the service with the correct status', (state, statusColor, statusText) => {
        const card = mount(SwSettingsServicesServiceCard, {
            props: {
                service: createService({ state: state }),
            },
            global: {
                stubs: {
                    SwColorBadge,
                    SwExtensionIcon: {
                        template: '<div><img :src="src" :alt="alt" /></div>',
                        props: ['src', 'alt'],
                    },
                    SwStatus,
                    MtModalAction,
                    MtModal,
                    MtModalRoot,
                    MtModalTrigger,
                    SwExtensionPermissionsModal: true,
                },
            },
        });

        expect(card.findComponent(SwStatus).props().color).toBe(statusColor);
        expect(card.find('.sw-settings-services-service-card__header').html()).toContain(
            `sw-settings-services.service-card.status-${statusText}`,
        );
    });

    it.each([
        ['service-icon', 'data:image/png;base64, service-icon'],
        [null, 'administration/administration/static/img/services/extension-icon-placeholder.svg'],
    ])('displays the service with the correct icon and version', (icon, expected) => {
        const card = mount(SwSettingsServicesServiceCard, {
            props: {
                service: createService({ icon: icon }),
            },
            global: {
                stubs: {
                    SwColorBadge,
                    SwExtensionIcon: {
                        template: '<div><img :src="src" :alt="alt" /></div>',
                        props: ['src', 'alt'],
                    },
                    SwStatus,
                    MtModalAction,
                    MtModal,
                    MtModalRoot,
                    MtModalTrigger,
                    SwExtensionPermissionsModal: true,
                },
            },
        });

        expect(card.find('img').attributes('src')).toBe(expected);
        expect(card.vm.readableVersion).toBe('1.0.0');
    });

    it('opens the deactivation modal and deactivates a service', async () => {
        Shopware.Service('shopwareServicesService').deactivateService.mockImplementationOnce(() => {
            return Promise.resolve();
        });

        const card = mount(SwSettingsServicesServiceCard, {
            props: {
                service: createService(),
            },
            global: {
                stubs: {
                    SwColorBadge,
                    SwExtensionIcon: {
                        template: '<div><img :src="src" :alt="alt" /></div>',
                        props: ['src', 'alt'],
                    },
                    SwStatus,
                    MtModalAction,
                    MtModal,
                    MtModalRoot,
                    MtModalTrigger,
                    MtPopover,
                    MtPopoverItem,
                    MtButton,
                    SwExtensionPermissionsModal: true,
                },
            },
        });

        jest.spyOn(card.vm, '_reloadPage').mockImplementation(() => {});

        const popover = card.findComponent(MtPopover);
        expect(popover.exists()).toBeTruthy();

        const popoverButton = popover.findComponent(MtButton);
        expect(popoverButton.exists()).toBeTruthy();
        expect(popoverButton.isVisible()).toBeTruthy();

        await popoverButton.trigger('click');

        const popoverItem = card.findComponent(MtPopoverItem);
        expect(popoverItem.exists()).toBeTruthy();
        expect(popoverItem.isVisible()).toBeTruthy();

        await popoverItem.trigger('click');
        // Wait 32ms for debounce
        await new Promise((resolve) => {
            setTimeout(resolve, 32);
        });

        const modal = card.findComponent(MtModal);
        expect(modal.exists()).toBeTruthy();
        expect(modal.isVisible()).toBeTruthy();

        const deactivateButton = modal.findAllComponents(MtModalAction)[1];
        expect(deactivateButton.exists()).toBeTruthy();
        expect(deactivateButton.text()).toBe('sw-settings-services.general.deactivate');

        await deactivateButton.trigger('click');

        expect(Shopware.Service('shopwareServicesService').deactivateService).toHaveBeenCalledWith('service-name');
        expect(card.vm._reloadPage).toHaveBeenCalled();
    });

    it('shows a disabled deactivate hint for services whose state may not be changed manually', async () => {
        const card = mount(SwSettingsServicesServiceCard, {
            props: {
                service: createService({
                    requirements: ['shopware_account'],
                    state_change_permitted: false,
                }),
            },
            global: {
                stubs: {
                    SwColorBadge,
                    SwExtensionIcon: {
                        template: '<div><img :src="src" :alt="alt" /></div>',
                        props: ['src', 'alt'],
                    },
                    SwStatus,
                    MtModalAction,
                    MtModal,
                    MtModalRoot,
                    MtModalTrigger,
                    MtPopover,
                    MtPopoverItem,
                    MtButton,
                    SwExtensionPermissionsModal: true,
                },
            },
        });

        expect(card.vm.stateChangePermitted).toBe(false);
        expect(card.text()).not.toContain('sw-settings-services.service-card.cannot-remove-or-deactivate');

        await card.findComponent(MtPopover).findComponent(MtButton).trigger('click');
        // Wait 32ms for debounce
        await new Promise((resolve) => {
            setTimeout(resolve, 32);
        });

        const popoverItems = card.findAllComponents(MtPopoverItem);
        const popoverItemLabels = popoverItems.map((popoverItem) => popoverItem.text());
        const disabledDeactivateHint = popoverItems.find((popoverItem) => {
            return popoverItem.text() === 'sw-settings-services.service-card.cannot-remove-or-deactivate';
        });

        expect(popoverItemLabels).not.toContain('sw-settings-services.general.deactivate');
        expect(popoverItemLabels).toContain('sw-settings-services.service-card.cannot-remove-or-deactivate');
        expect(popoverItemLabels).toContain('sw-settings-services.service-card.permissions');
        expect(disabledDeactivateHint.props('disabled')).toBe(true);
    });

    it('does not offer manual activation for inactive services whose state may not be changed manually', async () => {
        const card = mount(SwSettingsServicesServiceCard, {
            props: {
                service: createService({
                    active: false,
                    state_change_permitted: false,
                }),
            },
            global: {
                stubs: {
                    SwColorBadge,
                    SwExtensionIcon: {
                        template: '<div><img :src="src" :alt="alt" /></div>',
                        props: ['src', 'alt'],
                    },
                    SwStatus,
                    MtModalAction,
                    MtModal,
                    MtModalRoot,
                    MtModalTrigger,
                    MtPopover,
                    MtPopoverItem,
                    MtButton,
                    SwExtensionPermissionsModal: true,
                },
            },
        });

        await card.findComponent(MtPopover).findComponent(MtButton).trigger('click');
        // Wait 32ms for debounce
        await new Promise((resolve) => {
            setTimeout(resolve, 32);
        });

        const popoverItemLabels = card.findAllComponents(MtPopoverItem).map((popoverItem) => popoverItem.text());

        expect(popoverItemLabels).not.toContain('sw-settings-services.general.activate');
        expect(popoverItemLabels).toContain('sw-settings-services.service-card.cannot-remove-or-deactivate');
    });

    it('activates a service', async () => {
        Shopware.Service('shopwareServicesService').activateService.mockImplementationOnce(() => {
            return Promise.resolve();
        });

        const card = mount(SwSettingsServicesServiceCard, {
            props: {
                service: createService({ active: false }),
            },
            global: {
                stubs: {
                    SwColorBadge,
                    SwExtensionIcon: {
                        template: '<div><img :src="src" :alt="alt" /></div>',
                        props: ['src', 'alt'],
                    },
                    SwStatus,
                    MtModalAction,
                    MtModal,
                    MtModalRoot,
                    MtModalTrigger,
                    MtPopover,
                    MtPopoverItem,
                    MtButton,
                    SwExtensionPermissionsModal: true,
                },
            },
        });

        jest.spyOn(card.vm, '_reloadPage').mockImplementation(() => {});

        const popover = card.findComponent(MtPopover);
        expect(popover.exists()).toBeTruthy();

        const popoverButton = popover.findComponent(MtButton);
        expect(popoverButton.exists()).toBeTruthy();
        expect(popoverButton.isVisible()).toBeTruthy();

        await popoverButton.trigger('click');
        // Wait 32ms for debounce
        await new Promise((resolve) => {
            setTimeout(resolve, 32);
        });

        const popoverItem = card
            .findAllComponents(MtPopoverItem)
            .find((pi) => pi.text() === 'sw-settings-services.general.activate');
        expect(popoverItem).toBeDefined();

        await popoverItem.trigger('click');
        // Wait 32ms for debounce
        await new Promise((resolve) => {
            setTimeout(resolve, 32);
        });

        expect(Shopware.Service('shopwareServicesService').activateService).toHaveBeenCalledWith('service-name');
        expect(card.vm._reloadPage).toHaveBeenCalled();
    });

    it('shows permissions modal for a service', async () => {
        Shopware.Service('shopwareServicesService').getCategorizedPermissions.mockImplementationOnce(async () => ({
            permissions: {
                order: [
                    {
                        extensions: [],
                        entity: 'order',
                        operation: 'read',
                    },
                    {
                        extensions: [],
                        entity: 'order_line_item',
                        operation: 'read',
                    },
                ],
            },
        }));

        const card = mount(SwSettingsServicesServiceCard, {
            props: {
                service: createService({
                    active: false,
                    domains: ['url-to-app-server'],
                }),
            },
            global: {
                stubs: {
                    SwColorBadge,
                    SwExtensionIcon: {
                        template: '<div><img :src="src" :alt="alt" /></div>',
                        props: ['src', 'alt'],
                    },
                    SwStatus,
                    MtModalAction,
                    MtModal,
                    MtModalRoot,
                    MtModalTrigger,
                    MtPopover,
                    MtPopoverItem,
                    MtButton,
                    SwExtensionPermissionsModal: {
                        name: 'sw-extension-permissions-modal',
                        template: '<div>permissions modal stub</div>',
                        props: ['extension-label', 'permissions', 'domains'],
                    },
                },
            },
        });

        expect(card.find('sw-extension-permissions-modal').exists()).toBe(false);

        const popover = card.findComponent(MtPopover);
        expect(popover.exists()).toBeTruthy();

        const popoverButton = popover.findComponent(MtButton);
        expect(popoverButton.exists()).toBeTruthy();
        expect(popoverButton.isVisible()).toBeTruthy();

        await popoverButton.trigger('click');
        // Wait 32ms for debounce
        await new Promise((resolve) => {
            setTimeout(resolve, 32);
        });

        const popoverItem = card
            .findAllComponents(MtPopoverItem)
            .find((pi) => pi.text() === 'sw-settings-services.service-card.permissions');
        expect(popoverItem).toBeDefined();

        await popoverItem.trigger('click');
        // Wait 32ms for debounce
        await new Promise((resolve) => {
            setTimeout(resolve, 32);
        });

        const permissionsModal = card.getComponent({ name: 'sw-extension-permissions-modal' });

        expect(permissionsModal.props('extensionLabel')).toBe('service-label');
        expect(permissionsModal.props('domains')).toEqual(['url-to-app-server']);
        expect(permissionsModal.props('permissions')).toEqual({
            order: [
                {
                    extensions: [],
                    entity: 'order',
                    operation: 'read',
                },
                {
                    extensions: [],
                    entity: 'order_line_item',
                    operation: 'read',
                },
            ],
        });
    });

    const createHealth = (overrides = {}) => ({
        name: 'service-name',
        status: 'OK',
        reason: null,
        lastStatusCode: null,
        lastAttemptAt: null,
        lastAppActivityAt: null,
        ...overrides,
    });

    const mountWithHealth = (service, health) => {
        return mount(SwSettingsServicesServiceCard, {
            props: { service, health },
            global: {
                stubs: {
                    SwColorBadge,
                    SwExtensionIcon: true,
                    SwStatus,
                    MtButton,
                    MtModalAction,
                    MtModal,
                    MtModalRoot,
                    MtModalTrigger,
                    SwExtensionPermissionsModal: true,
                },
            },
        });
    };

    // the modal content is teleported to the body, so its text is read from the document
    const openHealthModal = async (card) => {
        await card.get('.sw-settings-services-service-card__health-details-trigger').trigger('click');
        await new Promise((resolve) => {
            setTimeout(resolve, 32);
        });

        return document.body.querySelector('.sw-settings-services-service-card__health-modal-content');
    };

    it.each([
        ['ERROR', 'credentials_rejected', 'red', 'connection-problem', 'credentials_rejected'],
        ['ERROR', 'shop_id_mismatch', 'red', 'connection-problem', 'shop_id_mismatch'],
        ['ERROR', 'registration_incomplete', 'red', 'connection-problem', 'registration_incomplete'],
        ['WARNING', 'delivery_failing', 'orange', 'needs-attention', 'delivery_failing'],
        ['WARNING', 'webhooks_suspended', 'orange', 'needs-attention', 'webhooks_suspended'],
        ['WARNING', 'no_app_activity', 'orange', 'no-recent-activity', 'no_app_activity'],
        ['ERROR', 'a_reason_added_later', 'red', 'connection-problem', 'unknown'],
        ['WARNING', 'a_reason_added_later', 'orange', 'needs-attention', 'unknown'],
    ])(
        'shows the health of an active service in its pill and explains it in a dialog: %s / %s',
        async (status, reason, statusColor, statusText, messageKey) => {
            const card = mountWithHealth(createService(), createHealth({ status, reason }));

            expect(card.findComponent(SwStatus).props().color).toBe(statusColor);
            expect(card.find('.sw-settings-services-service-card__header').text()).toContain(
                `sw-settings-services.service-card.status-${statusText}`,
            );

            const modalContent = await openHealthModal(card);

            expect(card.findAllComponents(MtModal).map((modal) => modal.props('title'))).toContain(
                `sw-settings-services.service-card.status-${statusText}`,
            );
            expect(modalContent.textContent).toContain(`sw-settings-services.service-card.health-reason.${messageKey}`);

            card.unmount();
        },
    );

    it('lists the last attempt and response code for support when they are known', async () => {
        const card = mountWithHealth(
            createService(),
            createHealth({
                status: 'ERROR',
                reason: 'credentials_rejected',
                lastStatusCode: 401,
                lastAttemptAt: '2026-10-19 12:00:00.000',
            }),
        );

        const modalContent = await openHealthModal(card);

        expect(modalContent.querySelector('.sw-settings-services-service-card__health-details').textContent).toContain(
            'sw-settings-services.service-card.health-support-details',
        );

        card.unmount();
    });

    it('shows no support details when nothing is known about the last attempt', async () => {
        const card = mountWithHealth(createService(), createHealth({ status: 'WARNING', reason: 'webhooks_suspended' }));

        const modalContent = await openHealthModal(card);

        expect(modalContent).not.toBeNull();
        expect(modalContent.querySelector('.sw-settings-services-service-card__health-details')).toBeNull();

        card.unmount();
    });

    it.each([
        ['active', 'OK', null],
        ['active', 'SKIPPED', 'inactive'],
        ['active', 'UNKNOWN', 'no_signal'],
        ['active', 'FAILURE', null],
        ['inactive', 'ERROR', 'credentials_rejected'],
        ['pending_permissions', 'WARNING', 'delivery_failing'],
    ])('keeps the service state when the health has nothing to add: %s / %s', (state, status, reason) => {
        const card = mountWithHealth(createService({ state }), createHealth({ status, reason }));
        const expectedColor = { active: 'green', inactive: 'red', pending_permissions: 'orange' }[state];

        expect(card.findComponent(SwStatus).props().color).toBe(expectedColor);
        expect(card.find('.sw-settings-services-service-card__health-details-trigger').exists()).toBe(false);
    });

    it('keeps the service state when no health is known', () => {
        const card = mountWithHealth(createService(), null);

        expect(card.findComponent(SwStatus).props().color).toBe('green');
        expect(card.find('.sw-settings-services-service-card__health-details-trigger').exists()).toBe(false);
    });
});
