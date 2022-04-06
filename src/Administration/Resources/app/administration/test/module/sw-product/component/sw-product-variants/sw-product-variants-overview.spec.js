import { createLocalVue, shallowMount } from '@vue/test-utils';
import Vuex from 'vuex';
import 'src/module/sw-product/component/sw-product-variants/sw-product-variants-overview';

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.use(Vuex);
    return shallowMount(Shopware.Component.build('sw-product-variants-overview'), {
        localVue,
        propsData: {
            selectedGroups: []
        },
        mocks: {
            $route: {
                query: {}
            },
        },
        provide: {
            repositoryFactory: {},
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }
                    return privileges.includes(identifier);
                }
            },
            searchRankingService: {}
        },
        stubs: {
            'sw-container': {
                template: '<div class="sw-container"><slot></slot></div>'
            },
            'sw-simple-search-field': true,
            'sw-button': {
                template: '<button @click="$emit(\'click\', $event)"><slot></slot></button>'
            },
            'sw-icon': true,
            'sw-context-menu': true,
            'sw-tree': true,
            'sw-data-grid': {
                props: ['dataSource', 'columns'],
                data() {
                    return {
                        selection: []
                    };
                },
                template: `
                    <div class="sw-data-grid">
                        <slot name="bulk"></slot>
                        <input class="sw-data-grid__select-all" @change="selectAll" />
                        <template v-for="item in dataSource">
                            <slot name="actions" v-bind="{ item }"></slot>
                        </template>
                    </div>
                `,
                methods: {
                    selectAll() {
                        this.selection = {};
                        this.dataSource.forEach(item => {
                            this.selection[item.id] = item;
                        });
                    }
                }
            },
            'sw-context-menu-item': {
                template: '<div class="sw-context-menu-item" @click="$emit(\'click\')"><slot></slot></div>'
            },
            'sw-pagination': true,
            'sw-modal': {
                template: '<div class="sw-modal"><slot></slot><slot name="modal-footer"></slot></div>'
            },
        }
    });
}
describe('src/module/sw-product/component/sw-product-variants/sw-product-variants-overview', () => {
    const consoleError = console.error;
    beforeAll(() => {
        const product = {
            media: []
        };
        product.getEntityName = () => 'T-Shirt';
        Shopware.State.registerModule('swProductDetail', {
            namespaced: true,
            state: () => ({
                product: product,
                currencies: [],
                variants: [
                    {
                        id: 1,
                        name: null,
                        options: [
                            {
                                id: 1,
                                name: '30',
                                translated: {
                                    name: '30',
                                },
                                groupId: 'size-group-id',
                            },
                        ],
                    },
                    {
                        id: 2,
                        name: null,
                        options: [
                            {
                                id: 2,
                                name: '32',
                                translated: {
                                    name: '32',
                                },
                                groupId: 'size-group-id',
                            },
                        ],
                    },
                ],
            }),
            getters: {
                isLoading: () => false
            },
            mutations: {
                setVariants(state, variants) {
                    state.variants = variants;
                },
            },
        });
    });

    afterAll(() => {
        Shopware.State.unregisterModule('swProductDetail');
    });

    beforeEach(() => {
        console.error = jest.fn();
    });

    afterEach(() => {
        console.error = consoleError;
    });

    it('should be a Vue.JS component', async () => {
        const wrapper = createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should have an disabled generate variants button', async () => {
        const wrapper = createWrapper();
        const generateVariantsButton = wrapper.find('.sw-product-variants__generate-action');
        expect(generateVariantsButton.exists()).toBeTruthy();
        expect(generateVariantsButton.attributes().disabled).toBeTruthy();
    });

    it('should have an enabled generate variants button', async () => {
        const wrapper = createWrapper([
            'product.creator'
        ]);
        const generateVariantsButton = wrapper.find('.sw-product-variants__generate-action');
        expect(generateVariantsButton.exists()).toBeTruthy();
        expect(generateVariantsButton.attributes().disabled).toBeFalsy();
    });

    it('should allow inline editing', async () => {
        const wrapper = createWrapper([
            'product.editor'
        ]);
        const dataGrid = wrapper.find('.sw-product-variants-overview__data-grid');
        expect(dataGrid.attributes()['allow-inline-edit']).toBeTruthy();
    });

    it('should disallow inline editing', async () => {
        const wrapper = createWrapper();
        const dataGrid = wrapper.find('.sw-product-variants-overview__data-grid');
        expect(dataGrid.attributes()['allow-inline-edit']).toBeFalsy();
    });

    it('should enable selection deleting of list variants', async () => {
        const wrapper = createWrapper([
            'product.deleter'
        ]);
        const dataGrid = wrapper.find('.sw-product-variants-overview__data-grid');
        expect(dataGrid.attributes()['show-selection']).toBeTruthy();
    });

    it('should be able to turn on delete confirmation modal', async () => {
        const wrapper = createWrapper([
            'product.deleter'
        ]);

        const deleteContextButton = wrapper.find('.sw-context-menu-item[variant="danger"]');
        await deleteContextButton.trigger('click');

        await wrapper.vm.$forceUpdate();

        const deleteModal = wrapper.find('.sw-product-variants-overview__delete-modal');
        expect(deleteModal.exists()).toBeTruthy();

        expect(wrapper.find('.sw-product-variants-overview__modal--confirm-delete-text').text())
            .toBe('sw-product.variations.generatedListDeleteModalMessage');
    });

    it('should not be able to turn on delete confirmation modal', () => {
        const wrapper = createWrapper([
            'product.editor',
        ]);

        const deleteContextButton = wrapper.find('.sw-context-menu-item[variant="danger"]');
        expect(deleteContextButton.attributes().disabled).toBe('disabled');
    });

    it('should be able to delete variants', async () => {
        const wrapper = createWrapper([
            'product.deleter'
        ]);

        const selectAllInput = wrapper.find('.sw-data-grid__select-all');
        await selectAllInput.trigger('change');

        const deleteVariantsButton = wrapper.find('.sw-product-variants-overview__bulk-delete-action');
        expect(deleteVariantsButton.exists()).toBeTruthy();

        await deleteVariantsButton.trigger('click');

        const deleteModal = wrapper.find('.sw-product-variants-overview__delete-modal');

        expect(deleteModal.exists()).toBeTruthy();
        expect(wrapper.find('.sw-product-variants-overview__modal--confirm-delete-text').text())
            .toBe('sw-product.variations.generatedListDeleteModalMessagePlural');
    });

    it('should not be able to delete variants', async () => {
        const wrapper = createWrapper([
            'product.editor',
        ]);

        const deleteVariantsButton = wrapper.find('.sw-product-variants-overview__bulk-delete-action');
        expect(deleteVariantsButton.exists()).toBeFalsy();
    });
});
