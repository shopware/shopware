/*
 * @package inventory
 */

import { createLocalVue, shallowMount } from '@vue/test-utils';
import swProductAddPropertiesModal from 'src/module/sw-product/component/sw-product-add-properties-modal';

Shopware.Component.register('sw-product-add-properties-modal', swProductAddPropertiesModal);

async function createWrapper() {
    const localVue = createLocalVue();

    return shallowMount(await Shopware.Component.build('sw-product-add-properties-modal'), {
        localVue,
        stubs: {
            'sw-modal': true,
            'sw-container': true,
            'sw-card-section': true,
            'sw-grid': true,
            'sw-empty-state': true,
            'sw-simple-search-field': true,
            'sw-property-search': true,
            'sw-pagination': true,
            'sw-loader': true,
            'sw-button': true,
        },
        provide: {
            repositoryFactory: {
                create: () => ({
                    search: () => {
                        return Promise.resolve();
                    },
                }),
            },
        },
        propsData: {
            newProperties: [],
        },
    });
}

describe('src/module/sw-product/component/sw-product-add-properties-modal', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.JS component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should emit an event when pressing on cancel button', async () => {
        wrapper.vm.onCancel();

        const emitted = wrapper.emitted()['modal-cancel'];
        expect(emitted).toBeTruthy();
    });

    it('should emit an event when pressing on save button', async () => {
        wrapper.vm.onSave();

        const emitted = wrapper.emitted()['modal-save'];
        expect(emitted).toBeTruthy();
    });
});
