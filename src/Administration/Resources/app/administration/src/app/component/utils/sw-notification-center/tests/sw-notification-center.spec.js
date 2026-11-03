/**
 * @sw-package framework
 */

import { DOMWrapper, mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';

const notification = {
    visited: false,
    metadata: {},
    isLoading: false,
    uuid: '018d0c7c90f47a228894d117c9b442bc',
    timestamp: '2024-01-15T09:38:26.676Z',
    variant: 'error',
    message: 'Network Error',
};

async function createWrapper() {
    return mount(await wrapTestComponent('sw-notification-center', { sync: true }), {
        attachTo: document.body,
        global: {
            stubs: {
                'sw-notification-center-item': await wrapTestComponent('sw-notification-center-item'),
                'sw-time-ago': await wrapTestComponent('sw-time-ago'),
                'sw-loader': await wrapTestComponent('sw-loader'),
            },
        },
    });
}

function panel() {
    return new DOMWrapper(document.body);
}

describe('src/app/component/utils/sw-notification-center', () => {
    let wrapper;

    beforeEach(() => {
        setActivePinia(createPinia());
    });

    afterEach(() => {
        wrapper?.unmount();
        document.body.innerHTML = '';
    });

    it('should show empty state', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        await wrapper.find('.sw-notification-center__context-button').trigger('click');
        await flushPromises();

        expect(panel().find('.sw-notification-center__empty-state-wrapper').isVisible()).toBe(true);
        expect(panel().findAll('.sw-notification-center-item')).toHaveLength(0);

        expect(panel().find('.sw-notification-center__options-button').attributes('disabled')).toBeDefined();
    });

    it('should enable the options menu when notifications are present', async () => {
        Shopware.Store.get('notification').setNotifications({
            [notification.uuid]: notification,
        });

        wrapper = await createWrapper();
        await flushPromises();

        await wrapper.find('.sw-notification-center__context-button').trigger('click');
        await flushPromises();

        expect(panel().find('.sw-notification-center__options-button').attributes('disabled')).toBeUndefined();
    });

    it('should show notifications', async () => {
        Shopware.Store.get('notification').setNotifications({
            [notification.uuid]: notification,
        });

        wrapper = await createWrapper();
        await flushPromises();

        await wrapper.find('.sw-notification-center__context-button').trigger('click');
        await flushPromises();

        expect(panel().find('.sw-notification-center__empty-state-wrapper').isVisible()).toBe(false);
        expect(panel().findAll('.sw-notification-center-item')).toHaveLength(1);
    });

    it('should show no notifications after clearing them', async () => {
        Shopware.Store.get('notification').setNotifications({
            [notification.uuid]: notification,
        });

        wrapper = await createWrapper();
        await flushPromises();

        await wrapper.find('.sw-notification-center__context-button').trigger('click');
        await flushPromises();

        await panel().find('.sw-notification-center__options-button').trigger('click');
        await flushPromises();

        await panel().find('.mt-action-menu-item--variant-critical').trigger('click');
        await flushPromises();

        const deleteButton = panel()
            .findAll('.sw-modal button')
            .find((button) => button.text() === 'global.default.delete');
        await deleteButton.trigger('click');
        await flushPromises();

        await wrapper.find('.sw-notification-center__context-button').trigger('click');
        await flushPromises();

        expect(panel().find('.sw-notification-center__empty-state-wrapper').isVisible()).toBe(true);
        expect(panel().findAll('.sw-notification-center-item')).toHaveLength(0);
    });

    it('should ring the empty-state bell when its button is clicked', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        await wrapper.find('.sw-notification-center__context-button').trigger('click');
        await flushPromises();

        expect(wrapper.vm.isBellRinging).toBe(false);
        expect(panel().find('.sw-notification-center__empty-state--ringing').exists()).toBe(false);

        await panel().find('.sw-notification-center__empty-state-bell-button').trigger('click');
        await flushPromises();

        expect(wrapper.vm.isBellRinging).toBe(true);
        expect(panel().find('.sw-notification-center__empty-state--ringing').exists()).toBe(true);
    });

    it('should close the delete modal when the panel is closed', async () => {
        Shopware.Store.get('notification').setNotifications({
            [notification.uuid]: notification,
        });

        wrapper = await createWrapper();
        await flushPromises();

        await wrapper.find('.sw-notification-center__context-button').trigger('click');
        await flushPromises();

        await panel().find('.sw-notification-center__options-button').trigger('click');
        await flushPromises();

        await panel().find('.mt-action-menu-item--variant-critical').trigger('click');
        await flushPromises();

        expect(panel().find('.sw-modal').exists()).toBe(true);

        await wrapper.find('.sw-notification-center__context-button').trigger('click');
        await flushPromises();
        await wrapper.find('.sw-notification-center__context-button').trigger('click');
        await flushPromises();

        expect(panel().find('.sw-modal').exists()).toBe(false);
    });

    it('should not reopen the options menu after the delete modal flow', async () => {
        Shopware.Store.get('notification').setNotifications({
            [notification.uuid]: notification,
        });

        wrapper = await createWrapper();
        await flushPromises();

        await wrapper.find('.sw-notification-center__context-button').trigger('click');
        await flushPromises();

        await panel().find('.sw-notification-center__options-button').trigger('click');
        await flushPromises();
        expect(panel().find('.mt-action-menu-item--variant-critical').exists()).toBe(true);

        await panel().find('.mt-action-menu-item--variant-critical').trigger('click');
        await flushPromises();

        await wrapper.vm.onCloseDeleteModal();
        await flushPromises();

        await wrapper.find('.sw-notification-center__context-button').trigger('click');
        await flushPromises();

        expect(panel().find('.mt-action-menu-item--variant-critical').exists()).toBe(false);
    });
});
