/**
 * @package inventory
 * @group disabledCompat
 */
import { mount } from '@vue/test-utils';

const { State } = Shopware;

const productMock = {
    id: 'productId',
    properties: [],
};

async function createWrapper() {
    return mount(await wrapTestComponent('sw-product-cross-selling-form', { sync: true }), {
        props: {
            crossSelling: {},
            allowEdit: false,
        },
        global: {
            stubs: {
                'sw-card': true,
                'sw-container': true,
                'sw-context-button': true,
                'sw-text-field': true,
                'sw-button': true,
                'sw-context-menu-item': true,
                'sw-switch-field': true,
                'sw-select-field': true,
                'sw-number-field': true,
                'sw-entity-single-select': true,
                'sw-icon': true,
                'sw-product-cross-selling-assignment': true,
                'sw-product-stream-modal-preview': true,
                'sw-modal': true,
                'sw-condition-tree': true,
            },
            provide: {
                repositoryFactory: {
                    create: () => {
                        return {
                            get: () => {
                                return Promise.resolve([]);
                            },
                            search: () => {
                                return Promise.resolve([]);
                            },
                        };
                    },
                },
                productStreamConditionService: {
                    search: () => {},
                },
            },
        },
    });
}

describe('module/sw-product/component/sw-product-cross-selling-form', () => {
    let wrapper;

    beforeAll(() => {
        State.registerModule('swProductDetail', {
            namespaced: true,
            state: {
                product: productMock,
            },
            getters: {
                isLoading: () => false,
            },
        });
    });

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    it('should get correct sorting types', async () => {
        wrapper = await createWrapper();
        await wrapper.setData({ productStream: {
            filters: {
                entity: 'product',
                source: 'source',
            },
        } });

        expect(wrapper.vm.sortingTypes).toEqual(
            [
                {
                    label: 'sw-product.crossselling.priceDescendingSortingType',
                    value: 'cheapestPrice:DESC',
                },
                {
                    label: 'sw-product.crossselling.priceAscendingSortingType',
                    value: 'cheapestPrice:ASC',
                },
                {
                    label: 'sw-product.crossselling.nameSortingType',
                    value: 'name:ASC',
                },
                {
                    label: 'sw-product.crossselling.releaseDateDescendingSortingType',
                    value: 'releaseDate:DESC',
                },
                {
                    label: 'sw-product.crossselling.releaseDateAscendingSortingType',
                    value: 'releaseDate:ASC',
                },
            ],
        );
    });
});
