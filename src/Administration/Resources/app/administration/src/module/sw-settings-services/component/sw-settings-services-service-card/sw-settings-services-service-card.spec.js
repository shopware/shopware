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

describe('src/module/sw-settings-services/component/sw-settings-services-service-card.ts', () => {
    let originalWindowLocation;

    beforeAll(() => {
        Shopware.Service().register('shopwareExtensionService', () => ({
            activateExtension: jest.fn(),
            deactivateExtension: jest.fn(),
        }));

        originalWindowLocation = window.location;

        Object.defineProperty(window, 'location', {
            configurable: true,
            value: { reload: jest.fn() },
        });
    });

    afterAll(() => {
        Object.defineProperty(window, 'location', {
            configurable: true,
            value: { reload: originalWindowLocation },
        });
    });

    it.each([
        [
            true,
            [],
            'green',
            'active',
        ],
        [
            false,
            [],
            'red',
            'inactive',
        ],
        [
            false,
            ['order:read'],
            'red',
            'inactive',
        ],
        [
            true,
            ['order:read'],
            'orange',
            'awaiting-permissions',
        ],
    ])('displays the service with the correct status', (active, requestedPrivileges, statusColor, statusText) => {
        const card = mount(SwSettingsServicesServiceCard, {
            props: {
                service: {
                    id: 'service-id',
                    active: active,
                    name: 'service-name',
                    label: 'service-label',
                    icon: 'service-icon',
                    description: 'service-description',
                    updated_at: '2025-07-08 11:21:44.819',
                    version: '1.0.0-b63f0ad27d1ee5a22871637a2ffcdc80',
                    requested_privileges: requestedPrivileges,
                    privileges: [],
                },
            },
            global: {
                stubs: {
                    SwColorBadge,
                    SwExtensionIcon: {
                        template: '<div><img :src="src" :alt="alt" /></div>',
                        props: [
                            'src',
                            'alt',
                        ],
                    },
                    SwStatus,
                    MtModalAction,
                    MtModal,
                    MtModalRoot,
                    MtModalTrigger,
                },
            },
        });

        expect(card.findComponent(SwStatus).props().color).toBe(statusColor);
        expect(card.find('.sw-settings-services-service-card__header').html()).toContain(
            `sw-settings-services.service-card.status-${statusText}`,
        );
    });

    it.each([
        [
            'service-icon',
            'data:image/png;base64, service-icon',
        ],
        [
            null,
            'administration/administration/static/img/services/extension-icon-placeholder.svg',
        ],
    ])('displays the service with the correct icon and version', (icon, expected) => {
        const card = mount(SwSettingsServicesServiceCard, {
            props: {
                service: {
                    id: 'service-id',
                    active: true,
                    name: 'service-name',
                    label: 'service-label',
                    icon: icon,
                    description: 'service-description',
                    updated_at: '2025-07-08 11:21:44.819',
                    version: '1.0.0-b63f0ad27d1ee5a22871637a2ffcdc80',
                    requested_privileges: [],
                    privileges: [],
                },
            },
            global: {
                stubs: {
                    SwColorBadge,
                    SwExtensionIcon: {
                        template: '<div><img :src="src" :alt="alt" /></div>',
                        props: [
                            'src',
                            'alt',
                        ],
                    },
                    SwStatus,
                    MtModalAction,
                    MtModal,
                    MtModalRoot,
                    MtModalTrigger,
                },
            },
        });

        expect(card.find('img').attributes('src')).toBe(expected);
        expect(card.vm.readableVersion).toBe('1.0.0');
    });

    it('opens the deactivation modal and deactivates a service', async () => {
        Shopware.Service('shopwareExtensionService').deactivateExtension.mockImplementationOnce(() => {
            return Promise.resolve();
        });

        const card = mount(SwSettingsServicesServiceCard, {
            props: {
                service: {
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
                },
            },
            global: {
                stubs: {
                    SwColorBadge,
                    SwExtensionIcon: {
                        template: '<div><img :src="src" :alt="alt" /></div>',
                        props: [
                            'src',
                            'alt',
                        ],
                    },
                    SwStatus,
                    MtModalAction,
                    MtModal,
                    MtModalRoot,
                    MtModalTrigger,
                    MtPopover,
                    MtPopoverItem,
                    MtButton,
                },
            },
        });

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

        const modal = card.findComponent(MtModal);
        expect(modal.exists()).toBeTruthy();
        expect(modal.isVisible()).toBeTruthy();

        const deactivateButton = modal.findAllComponents(MtModalAction)[1];
        expect(deactivateButton.exists()).toBeTruthy();
        expect(deactivateButton.text()).toBe('sw-settings-services.general.deactivate');

        await deactivateButton.trigger('click');

        expect(Shopware.Service('shopwareExtensionService').deactivateExtension).toHaveBeenCalledWith('service-name', 'app');
        expect(window.location.reload).toHaveBeenCalled();
    });

    it('activates a service', async () => {
        Shopware.Service('shopwareExtensionService').activateExtension.mockImplementationOnce(() => {
            return Promise.resolve();
        });

        const card = mount(SwSettingsServicesServiceCard, {
            props: {
                service: {
                    id: 'service-id',
                    active: false,
                    name: 'service-name',
                    label: 'service-label',
                    icon: 'service-icon',
                    description: 'service-description',
                    updated_at: '2025-07-08 11:21:44.819',
                    version: '1.0.0-b63f0ad27d1ee5a22871637a2ffcdc80',
                    requested_privileges: [],
                    privileges: [],
                },
            },
            global: {
                stubs: {
                    SwColorBadge,
                    SwExtensionIcon: {
                        template: '<div><img :src="src" :alt="alt" /></div>',
                        props: [
                            'src',
                            'alt',
                        ],
                    },
                    SwStatus,
                    MtModalAction,
                    MtModal,
                    MtModalRoot,
                    MtModalTrigger,
                    MtPopover,
                    MtPopoverItem,
                    MtButton,
                },
            },
        });

        const popover = card.findComponent(MtPopover);
        expect(popover.exists()).toBeTruthy();

        const popoverButton = popover.findComponent(MtButton);
        expect(popoverButton.exists()).toBeTruthy();
        expect(popoverButton.isVisible()).toBeTruthy();

        await popoverButton.trigger('click');

        const popoverItem = card.findComponent(MtPopoverItem);
        expect(popoverItem.exists()).toBeTruthy();
        expect(popoverItem.isVisible()).toBeTruthy();
        expect(popoverItem.text()).toBe('sw-settings-services.general.activate');

        await popoverItem.trigger('click');

        expect(Shopware.Service('shopwareExtensionService').activateExtension).toHaveBeenCalledWith('service-name', 'app');
        expect(window.location.reload).toHaveBeenCalled();
    });
});
