/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-notification-center', { sync: true }), {
        global: {
            stubs: {
                'sw-context-button': await wrapTestComponent('sw-context-button'),
                'sw-context-menu': await wrapTestComponent('sw-context-menu'),
                'sw-notification-center-item': await wrapTestComponent('sw-notification-center-item'),
                'sw-time-ago': await wrapTestComponent('sw-time-ago'),
                'sw-loader': await wrapTestComponent('sw-loader'),
                'sw-context-menu-item': await wrapTestComponent('sw-context-menu-item'),
                'sw-popover': {
                    template: '<div class="sw-popover"><slot></slot></div>',
                },
                'router-link': true,
            },
        },
    });
}

describe('src/app/component/utils/sw-notification-center', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('should show empty state', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.find('.mt-icon').trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-notification-center__empty-state').isVisible()).toBe(true);
        expect(wrapper.find('.sw-notification-center__empty-state--ringing').exists()).toBe(false);
        expect(wrapper.find('.mt-empty-state__headline').text()).toBe('global.notification-center.emptyText');
        expect(wrapper.find('.mt-empty-state__description').text()).toBe('global.notification-center.emptyDescription');
        expect(wrapper.find('.sw-notification-center__empty-state').attributes('role')).toBe('status');
        expect(wrapper.findAll('.sw-notification-center-item')).toHaveLength(0);
        expect(wrapper.find('.sw-notification-center__header .sw-context-button').exists()).toBe(false);
    });

    it('should ring the empty state bell only when clicking the bell button', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.find('.mt-icon').trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-notification-center__empty-state').exists()).toBe(true);
        expect(wrapper.find('.sw-notification-center__empty-state--ringing').exists()).toBe(false);

        const bellButton = wrapper.find('.sw-notification-center__empty-state-bell-button');
        expect(bellButton.attributes('aria-label')).toBe('global.notification-center.ringBellLabel');

        await bellButton.trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-notification-center__empty-state').exists()).toBe(true);
        expect(wrapper.find('.sw-notification-center__empty-state--ringing').exists()).toBe(true);
    });

    it('should show notifications', async () => {
        Shopware.Store.get('notification').setNotifications({
            '018d0c7c90f47a228894d117c9b442bc': {
                visited: false,
                metadata: {},
                isLoading: false,
                uuid: '018d0c7c90f47a228894d117c9b442bc',
                timestamp: '2024-01-15T09:38:26.676Z',
                variant: 'error',
                message: 'Network Error',
            },
        });

        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.find('.mt-icon').trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-notification-center__empty-state').isVisible()).toBe(false);
        expect(wrapper.findAll('.sw-notification-center-item')).toHaveLength(1);
        expect(wrapper.find('.sw-notification-center__header .sw-context-button').exists()).toBe(true);
    });

    it('should close notifications when clicking outside the flyout', async () => {
        const notificationId = '018d0c7c90f47a228894d117c9b442bc';
        Shopware.Store.get('notification').setNotifications({
            [notificationId]: {
                visited: false,
                metadata: {},
                isLoading: false,
                uuid: notificationId,
                timestamp: '2024-01-15T09:38:26.676Z',
                variant: 'info',
                message: 'Thumbnail generation done',
            },
        });

        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.find('.mt-icon').trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-context-menu').exists()).toBe(true);
        expect(Shopware.Store.get('notification').notifications[notificationId].visited).toBe(false);

        const outsideButton = document.createElement('button');
        document.body.appendChild(outsideButton);

        outsideButton.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        await flushPromises();

        expect(wrapper.find('.sw-context-menu').exists()).toBe(false);
        expect(Shopware.Store.get('notification').notifications[notificationId].visited).toBe(true);

        outsideButton.remove();
    });

    it('should show no notifications after clearing them', async () => {
        Shopware.Store.get('notification').setNotifications({
            '018d0c7c90f47a228894d117c9b442bc': {
                visited: false,
                metadata: {},
                isLoading: false,
                uuid: '018d0c7c90f47a228894d117c9b442bc',
                timestamp: '2024-01-15T09:38:26.676Z',
                variant: 'error',
                message: 'Network Error',
            },
        });

        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.find('.mt-icon').trigger('click');
        await flushPromises();

        // opening the delete modal this way, because doing it via the DOM closes the context menu
        await wrapper.vm.openDeleteModal();

        await wrapper.findByText('button', 'global.default.delete').trigger('click');
        await flushPromises();

        // re-opening the context menu, happens only in test
        await wrapper.find('.mt-icon').trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-notification-center__empty-state').isVisible()).toBe(true);
        expect(wrapper.findAll('.sw-notification-center-item')).toHaveLength(0);
        expect(wrapper.find('.sw-notification-center__header .sw-context-button').exists()).toBe(false);
    });
});
