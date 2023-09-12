/*
 * @package inventory
 */

import { mount } from '@vue/test-utils_v3';
import EntityCollection from 'src/core/data/entity-collection.data';
import Criteria from 'src/core/data/criteria.data';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-product-add-properties-modal', { sync: true }), {
        attachTo: document.body,
        global: {
            stubs: {
                'sw-modal': {
                    template: `
                        <div class="sw-modal">
                          <slot name="modal-header"></slot>
                          <slot></slot>
                          <slot name="modal-footer"></slot>
                        </div>
                    `,
                },
                'sw-container': await wrapTestComponent('sw-container'),
                'sw-card-section': await wrapTestComponent('sw-card-section'),
                'sw-grid': true,
                'sw-empty-state': await wrapTestComponent('sw-empty-state'),
                'sw-simple-search-field': await wrapTestComponent('sw-simple-search-field'),
                'sw-property-search': await wrapTestComponent('sw-property-search'),
                'sw-pagination': await wrapTestComponent('sw-pagination'),
                'sw-loader': await wrapTestComponent('sw-loader'),
                'sw-button': await wrapTestComponent('sw-button'),
                'sw-icon': await wrapTestComponent('sw-icon'),
                'sw-field': await wrapTestComponent('sw-field'),
                'sw-text-field': await wrapTestComponent('sw-text-field'),
                'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-field-error': await wrapTestComponent('sw-field-error'),
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
        },
        props: {
            newProperties: [],
        },
    });
}

describe('src/module/sw-product/component/sw-product-add-properties-modal', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
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
