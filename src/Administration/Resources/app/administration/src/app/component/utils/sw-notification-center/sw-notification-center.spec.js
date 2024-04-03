/**
 * @package admin
 */

import { mount } from '@vue/test-utils';
import notificationStore from 'src/app/state/notification.store';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-notification-center', { sync: true }), {
        global: {
            stubs: {
                'sw-context-button': await wrapTestComponent('sw-context-button'),
                'sw-icon': await wrapTestComponent('sw-icon'),
                'sw-context-menu': await wrapTestComponent('sw-context-menu'),
                'sw-notification-center-item': await wrapTestComponent('sw-notification-center-item'),
                'sw-time-ago': await wrapTestComponent('sw-time-ago'),
                'sw-button': await wrapTestComponent('sw-button'),
                'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated', { sync: true }),
                'sw-loader': await wrapTestComponent('sw-loader'),
            },
        },
    });
}

describe('src/app/component/utils/sw-notification-center', () => {
    beforeEach(() => {
        if (Shopware.State.get('notification') !== undefined) {
            Shopware.State.unregisterModule('notification');
        }

        Shopware.State.registerModule('notification', notificationStore);
    });

    it('should show empty state', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.find('.sw-icon').trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-notification-center__empty-text').isVisible()).toBe(true);
        expect(wrapper.findAll('.sw-notification-center-item')).toHaveLength(0);
    });

    it('should show notifications', async () => {
        Shopware.State.commit('notification/setNotifications', {
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

        await wrapper.find('.sw-icon').trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-notification-center__empty-text').isVisible()).toBe(false);
        expect(wrapper.findAll('.sw-notification-center-item')).toHaveLength(1);
    });

    it('should show no notifications after clearing them', async () => {
        Shopware.State.commit('notification/setNotifications', {
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

        await wrapper.find('.sw-icon').trigger('click');
        await flushPromises();

        // opening the delete modal this way, because doing it via the DOM closes the context menu
        await wrapper.vm.openDeleteModal();

        await wrapper.find('.sw-button--primary').trigger('click');
        await flushPromises();

        // re-opening the context menu, happens only in test
        await wrapper.find('.sw-icon').trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-notification-center__empty-text').isVisible()).toBe(true);
        expect(wrapper.findAll('.sw-notification-center-item')).toHaveLength(0);
    });
});
