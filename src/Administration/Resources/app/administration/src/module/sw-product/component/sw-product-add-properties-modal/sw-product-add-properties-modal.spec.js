/*
 * @package inventory
 */

import { createLocalVue, shallowMount } from '@vue/test-utils';
import swProductAddPropertiesModal from 'src/module/sw-product/component/sw-product-add-properties-modal';
import 'src/app/component/base/sw-modal';
import 'src/app/component/base/sw-container';
import 'src/app/component/base/sw-card-section';
import 'src/app/component/grid/sw-grid';
import 'src/app/component/base/sw-empty-state';
import 'src/app/component/base/sw-simple-search-field';
import 'src/app/component/base/sw-property-search';
import 'src/app/component/grid/sw-pagination';
import 'src/app/component/utils/sw-loader';
import 'src/app/component/base/sw-button';
import 'src/app/component/base/sw-icon';
import 'src/app/component/form/sw-field';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/field-base/sw-field-error';
import EntityCollection from 'src/core/data/entity-collection.data';
import Criteria from 'src/core/data/criteria.data';

Shopware.Component.register('sw-product-add-properties-modal', swProductAddPropertiesModal);

async function createWrapper() {
    const localVue = createLocalVue();

    return shallowMount(await Shopware.Component.build('sw-product-add-properties-modal'), {
        localVue,
        stubs: {
            'sw-modal': await Shopware.Component.build('sw-modal'),
            'sw-container': await Shopware.Component.build('sw-container'),
            'sw-card-section': await Shopware.Component.build('sw-card-section'),
            'sw-grid': true,
            'sw-empty-state': await Shopware.Component.build('sw-empty-state'),
            'sw-simple-search-field': await Shopware.Component.build('sw-simple-search-field'),
            'sw-property-search': await Shopware.Component.build('sw-property-search'),
            'sw-pagination': await Shopware.Component.build('sw-pagination'),
            'sw-loader': await Shopware.Component.build('sw-loader'),
            'sw-button': await Shopware.Component.build('sw-button'),
            'sw-icon': await Shopware.Component.build('sw-icon'),
            'sw-field': await Shopware.Component.build('sw-field'),
            'sw-text-field': await Shopware.Component.build('sw-text-field'),
            'sw-contextual-field': await Shopware.Component.build('sw-contextual-field'),
            'sw-block-field': await Shopware.Component.build('sw-block-field'),
            'sw-base-field': await Shopware.Component.build('sw-base-field'),
            'sw-field-error': await Shopware.Component.build('sw-field-error'),
        },
        provide: {
            repositoryFactory: {
                create: () => ({
                    search: () => {
                        return Promise.resolve(new EntityCollection(
                            'jest',
                            'jest',
                            Shopware.Context.api,
                            new Criteria(1),
                            [],
                            0,
                            [],
                        ));
                    },
                }),
            },
            shortcutService: {
                stopEventListener: () => {},
                startEventListener: () => {},
            },
            validationService: {},
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

    it('should return filters from filter registry', async () => {
        expect(wrapper.vm.assetFilter).toEqual(expect.any(Function));
    });
});
