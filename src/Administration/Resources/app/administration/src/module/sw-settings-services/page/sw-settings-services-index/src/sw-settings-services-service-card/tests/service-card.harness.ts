import { mount, type VueWrapper } from '@vue/test-utils';
import { defineComponent, type Component } from 'vue';
import { shopwareServicesService } from 'src/app/service-names';
import type { ServiceDescription } from '../dependencies';
import { getShopwareServicesService, useNotificationStore } from '../dependencies';
import SwSettingsServicesServiceCard from '../private';

/**
 * @sw-package framework
 */

/**
 * The fake services service the card talks to; each test sets what its calls return.
 *
 * @private
 */
export const servicesService = {
    activateService: jest.fn(),
    deactivateService: jest.fn(),
    getCategorizedPermissions: jest.fn(),
};

/**
 * Counts how often the card reloaded the page, which it does after a service changed its state.
 *
 * @private
 */
export const pageReload = jest.fn();

const { MtButton, MtModal, MtModalAction, MtModalRoot, MtModalTrigger, MtPopover, MtPopoverItem } = jest.requireActual<
    Record<string, Component>
>('@shopware-ag/meteor-component-library');

const wrappers: VueWrapper[] = [];

let status: Component;

// The modal that lists the permissions a service asks for.
const PermissionsModalStub = defineComponent({
    name: 'sw-extension-permissions-modal',
    props: ['extensionLabel', 'permissions', 'domains'],
    emits: ['modal-close'],
    template: '<div class="sw-extension-permissions-modal" />',
});

/**
 * A service as the services service lists it: active, at version 1.0.0, and the merchant may change its state.
 *
 * @private
 */
export function createService(overrides: Partial<ServiceDescription> = {}): ServiceDescription {
    return {
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
    } as ServiceDescription;
}

/**
 * Mounts the card for this service, with the menu, modals and status as the real components.
 *
 * @private
 */
export async function mountServiceCard(service: ServiceDescription): Promise<VueWrapper> {
    if (!getShopwareServicesService()) {
        /** The fake services service, for the tests only. @private */
        Shopware.Service().register(shopwareServicesService, () => servicesService as never);
    }

    const { default: card } = await SwSettingsServicesServiceCard.load();
    status = (await wrapTestComponent('sw-status', { sync: true })) as Component;
    const wrapper = mount(
        { ...card, methods: { ...card.methods, _reloadPage: pageReload } },
        {
            props: { service },
            global: {
                mocks: {
                    // Renders the parameters of a translation, so a test sees the values the card passes.
                    $t: (key: string, params?: Record<string, unknown>) =>
                        params ? `${key} ${JSON.stringify(params)}` : key,
                },
                stubs: {
                    'sw-status': status,
                    'sw-color-badge': (await wrapTestComponent('sw-color-badge', { sync: true })) as Component,
                    'sw-extension-icon': {
                        props: ['src', 'alt'],
                        template: '<img class="sw-extension-icon" :src="src" :alt="alt" />',
                    },
                    'mt-button': MtButton,
                    'mt-popover': MtPopover,
                    'mt-popover-item': MtPopoverItem,
                    'mt-modal': MtModal,
                    'mt-modal-root': MtModalRoot,
                    'mt-modal-trigger': MtModalTrigger,
                    'mt-modal-action': MtModalAction,
                    'sw-extension-permissions-modal': PermissionsModalStub,
                },
            },
        },
    );
    wrappers.push(wrapper);
    await flushPromises();

    return wrapper;
}

async function waitForMenu(): Promise<void> {
    // The popover opens and closes after a debounce of 30ms.
    await new Promise((resolve) => {
        setTimeout(resolve, 32);
    });
    await flushPromises();
}

/**
 * Returns the status the card shows: its color and label.
 *
 * @private
 */
export function getShownStatus(wrapper: VueWrapper): { color: string; label: string } {
    const shown = wrapper.findComponent(status);

    return { color: (shown.props() as { color: string }).color, label: shown.text() };
}

/**
 * Returns the source of the icon the card shows.
 *
 * @private
 */
export function getShownIcon(wrapper: VueWrapper): string | undefined {
    return wrapper.find('.sw-extension-icon').attributes('src');
}

/**
 * Returns the text in the footer of the card, with the update date and the version.
 *
 * @private
 */
export function getFooterText(wrapper: VueWrapper): string {
    return wrapper.find('.sw-settings-services-service-card__footer').text();
}

/**
 * Opens the context menu of the card.
 *
 * @private
 */
export async function openMenu(wrapper: VueWrapper): Promise<void> {
    await wrapper.findComponent(MtPopover).findComponent(MtButton).trigger('click');
    await waitForMenu();
}

/**
 * Returns the items of the open context menu, with whether each is disabled.
 *
 * @private
 */
export function getMenuItems(wrapper: VueWrapper): Array<{ label: string; disabled: boolean }> {
    return wrapper.findAllComponents(MtPopoverItem).map((item) => ({
        label: item.text(),
        disabled: (item.props() as { disabled?: boolean }).disabled === true,
    }));
}

/**
 * Clicks the item of the open context menu that has this label.
 *
 * @private
 */
export async function clickMenuItem(wrapper: VueWrapper, label: string): Promise<void> {
    const item = wrapper.findAllComponents(MtPopoverItem).find((popoverItem) => popoverItem.text() === label);
    if (!item) {
        throw new Error(`The menu has no item "${label}"`);
    }

    await item.trigger('click');
    await waitForMenu();
}

/**
 * Whether the modal that asks to confirm the deactivation is open.
 *
 * @private
 */
export function isDeactivateModalOpen(): boolean {
    // The modal renders into the document body.
    return document.body.querySelector('.sw-settings-services-service-card__deactivate-modal-content') !== null;
}

/**
 * Clicks a button in the footer of the deactivate modal: `cancel` or `deactivate`.
 *
 * @private
 */
export async function clickDeactivateModalButton(wrapper: VueWrapper, button: 'cancel' | 'deactivate'): Promise<void> {
    const actions = wrapper.findComponent(MtModal).findAllComponents(MtModalAction);
    await actions[button === 'cancel' ? 0 : 1].trigger('click');
    await flushPromises();
}

/**
 * Returns the permissions modal the card shows, which may not exist.
 *
 * @private
 */
export function getPermissionsModal(wrapper: VueWrapper) {
    return wrapper.findComponent(PermissionsModalStub);
}

/**
 * Lets the permissions modal ask to be closed, as its close button does.
 *
 * @private
 */
export async function closePermissionsModal(wrapper: VueWrapper): Promise<void> {
    getPermissionsModal(wrapper).vm.$emit('modal-close');
    await flushPromises();
}

/**
 * Records the notifications the card creates, from now on.
 *
 * @private
 */
export function recordNotifications(): jest.SpyInstance {
    return jest.spyOn(useNotificationStore(), 'createNotification');
}

/**
 * Unmounts every card the tests mounted and resets the fake services service and the page reloads.
 *
 * @private
 */
export function unmountServiceCards(): void {
    wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
    Object.values(servicesService).forEach((mock) => mock.mockReset());
    pageReload.mockReset();
    jest.restoreAllMocks();
}
