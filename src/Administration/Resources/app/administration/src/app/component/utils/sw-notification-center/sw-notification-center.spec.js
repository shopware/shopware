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
                'sw-context-button': await wrapTestComponent('sw-context-button'),
                'sw-context-menu': await wrapTestComponent('sw-context-menu'),
                'sw-notification-center-item': await wrapTestComponent('sw-notification-center-item'),
                'sw-time-ago': await wrapTestComponent('sw-time-ago'),
                'sw-loader': await wrapTestComponent('sw-loader'),
                'sw-context-menu-item': await wrapTestComponent('sw-context-menu-item'),
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

        expect(panel().find('.sw-notification-center__empty-text').isVisible()).toBe(true);
        expect(panel().findAll('.sw-notification-center-item')).toHaveLength(0);
    });

    it('should show notifications', async () => {
        Shopware.Store.get('notification').setNotifications({
            [notification.uuid]: notification,
        });

        wrapper = await createWrapper();
        await flushPromises();

        await wrapper.find('.sw-notification-center__context-button').trigger('click');
        await flushPromises();

        expect(panel().find('.sw-notification-center__empty-text').isVisible()).toBe(false);
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

        await panel().find('.sw-context-button').trigger('click');
        await flushPromises();

        await panel().find('.sw-context-menu-item--danger').trigger('click');
        await flushPromises();

        const deleteButton = panel()
            .findAll('.sw-modal button')
            .find((button) => button.text() === 'global.default.delete');
        await deleteButton.trigger('click');
        await flushPromises();

        await wrapper.find('.sw-notification-center__context-button').trigger('click');
        await flushPromises();

        expect(panel().find('.sw-notification-center__empty-text').isVisible()).toBe(true);
        expect(panel().findAll('.sw-notification-center-item')).toHaveLength(0);
    });
});
