/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

describe('src/app/component/structure/sw-modals-renderer', () => {
    let wrapper;
    let store;

    beforeEach(async () => {
        store = Shopware.Store.get('modals');
        store.modals = [];

        wrapper = mount(await wrapTestComponent('sw-modals-renderer', { sync: true }), {
            global: {
                stubs: {
                    'sw-modal': {
                        props: ['zIndex'],
                        template: '<div class="sw-modal-stub"><slot /></div>',
                    },
                    'sw-iframe-renderer': true,
                    'mt-button': true,
                },
            },
        });
    });

    it('forwards the modal z-index to sw-modal', async () => {
        store.openModal({
            locationId: 'test',
            closable: false,
            showHeader: false,
            showFooter: false,
            variant: 'x-large',
            zIndex: 2000,
            baseUrl: 'https://example.com',
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.findComponent({ name: 'sw-modal' }).props('zIndex')).toBe(2000);
    });
});
