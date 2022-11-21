import { shallowMount } from '@vue/test-utils';
import swProductVariantsOverview from 'src/module/sw-product/component/sw-product-variants/sw-product-variants-overview';

Shopware.Component.register('sw-product-variants-overview', swProductVariantsOverview);

async function createWrapper() {
    return shallowMount(await Shopware.Component.build('sw-product-variants-overview'), {
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
    beforeEach(async () => {
        global.activeAclRoles = [];

        const product = {
            media: []
        };
        product.getEntityName = () => 'T-Shirt';

        if (Shopware.State.get('swProductDetail')) {
            Shopware.State.unregisterModule('swProductDetail');
        }

        Shopware.State.registerModule('swProductDetail', {
            namespaced: true,
            state() {
                return {
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
                    taxes: [],
                };
            },
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

    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should have an disabled generate variants button', async () => {
        const wrapper = await createWrapper();
        const generateVariantsButton = wrapper.find('.sw-product-variants__generate-action');
        expect(generateVariantsButton.exists()).toBeTruthy();
        expect(generateVariantsButton.attributes().disabled).toBeTruthy();
    });

    it('should have an enabled generate variants button', async () => {
        global.activeAclRoles = ['product.creator'];

        const wrapper = await createWrapper();
        const generateVariantsButton = wrapper.find('.sw-product-variants__generate-action');
        expect(generateVariantsButton.exists()).toBeTruthy();
        expect(generateVariantsButton.attributes().disabled).toBeFalsy();
    });

    it('should allow inline editing', async () => {
        global.activeAclRoles = ['product.editor'];

        const wrapper = await createWrapper();
        const dataGrid = wrapper.find('.sw-product-variants-overview__data-grid');
        expect(dataGrid.attributes()['allow-inline-edit']).toBeTruthy();
    });

    it('should disallow inline editing', async () => {
        const wrapper = await createWrapper();
        const dataGrid = wrapper.find('.sw-product-variants-overview__data-grid');
        expect(dataGrid.attributes()['allow-inline-edit']).toBeFalsy();
    });

    it('should enable selection deleting of list variants', async () => {
        global.activeAclRoles = ['product.deleter'];

        const wrapper = await createWrapper();
        const dataGrid = wrapper.find('.sw-product-variants-overview__data-grid');
        expect(dataGrid.attributes()['show-selection']).toBeTruthy();
    });

    it('should be able to turn on delete confirmation modal', async () => {
        global.activeAclRoles = ['product.deleter'];

        const wrapper = await createWrapper();

        const deleteContextButton = wrapper.find('.sw-context-menu-item[variant="danger"]');
        await deleteContextButton.trigger('click');

        await wrapper.vm.$forceUpdate();

        const deleteModal = wrapper.find('.sw-product-variants-overview__delete-modal');
        expect(deleteModal.exists()).toBeTruthy();

        expect(wrapper.find('.sw-product-variants-overview__modal--confirm-delete-text').text())
            .toBe('sw-product.variations.generatedListDeleteModalMessage');
    });

    it('should not be able to turn on delete confirmation modal', async () => {
        global.activeAclRoles = ['product.editor'];

        const wrapper = await createWrapper();

        const deleteContextButton = wrapper.find('.sw-context-menu-item[variant="danger"]');
        expect(deleteContextButton.attributes().disabled).toBe('disabled');
    });

    it('should be able to delete variants', async () => {
        global.activeAclRoles = ['product.deleter'];

        const wrapper = await createWrapper();

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
        global.activeAclRoles = ['product.editor'];

        const wrapper = await createWrapper();

        const deleteVariantsButton = wrapper.find('.sw-product-variants-overview__bulk-delete-action');
        expect(deleteVariantsButton.exists()).toBeFalsy();
    });
});
