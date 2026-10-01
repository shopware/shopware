/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';

describe('src/app/component/utils/sw-notifications', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('should translate title when it is a translation key', async () => {
        const wrapper = mount(await wrapTestComponent('sw-notifications', { sync: true }), {
            global: {
                stubs: {
                    'mt-banner': {
                        template: '<div class="mt-banner"><slot /></div>',
                        props: [
                            'title',
                            'variant',
                            'closable',
                            'notificationIndex',
                        ],
                    },
                    'mt-button': true,
                },
                mocks: {
                    $te: () => true,
                    $t: (key) => `Translated: ${key}`,
                    $sanitize: (value) => value,
                },
            },
        });

        const store = Shopware.Store.get('notification');
        store.growlNotifications = {
            'test-uuid': {
                uuid: 'test-uuid',
                title: 'global.default.error',
                message: 'Test Message',
                variant: 'error',
                actions: [],
            },
        };

        await flushPromises();

        expect(wrapper.findComponent('.mt-banner').props('title')).toBe('Translated: global.default.error');
    });

    it('should not translate missing notification titles', async () => {
        const wrapper = mount(await wrapTestComponent('sw-notifications', { sync: true }), {
            global: {
                stubs: {
                    'mt-banner': {
                        template: '<div class="mt-banner"><slot /></div>',
                        props: [
                            'title',
                            'variant',
                            'closable',
                            'notificationIndex',
                        ],
                    },
                    'mt-button': true,
                },
                mocks: {
                    $t: (key) => {
                        if (key === undefined) {
                            throw new Error('Missing translation key');
                        }

                        return `Translated: ${key}`;
                    },
                    $sanitize: (value) => value,
                },
            },
        });

        const store = Shopware.Store.get('notification');
        store.growlNotifications = {
            'test-uuid': {
                uuid: 'test-uuid',
                message: 'Test Message',
                variant: 'info',
                actions: [],
            },
        };

        await flushPromises();

        expect(wrapper.findComponent('.mt-banner').props('title')).toBe('');
    });
});
