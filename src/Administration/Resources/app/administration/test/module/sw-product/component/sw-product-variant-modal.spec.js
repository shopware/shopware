import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-product/component/sw-product-variant-modal';

function getOptions() {
    return [
        {
            name: 'b',
            translated: {
                name: 'b'
            },
            group: {
                translated: {
                    name: 'color'
                }
            },
            position: 1
        },
        {
            name: 'c',
            translated: {
                name: 'c'
            },
            group: {
                translated: {
                    name: 'size'
                }
            },
            position: 5
        },
        {
            name: 'a',
            translated: {
                name: 'a'
            },
            group: {
                translated: {
                    name: 'material'
                }
            },
            position: 1
        }
    ];
}

function getVariants(returnCurrency = true) {
    return {
        price: !returnCurrency ? null : [
            {
                currencyId: 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
                net: 24,
                gross: 24,
                linked: true,
                listPrice: null,
                extensions: []
            }
        ],
        childCount: 2,
        name: 'random product',
        translated: {
            name: 'random product'
        },
        id: '72bfaf5d90214ce592715a9649d8760a',
        options: getOptions()
    };
}

function createWrapper() {
    return shallowMount(Shopware.Component.build('sw-product-variant-modal'), {
        propsData: {
            productEntity: {
                price: [
                    {
                        currencyId: 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
                        net: 12,
                        gross: 12,
                        linked: true,
                        listPrice: null,
                        extensions: []
                    }
                ],
                productNumber: 'SW10000',
                childCount: 2,
                name: 'random product',
                translated: {
                    name: 'name'
                },
                id: '72bfaf5d90214ce592715a9649d8760a'
            }
        },
        mocks: {
            $t: key => key,
            $tc: key => key
        },
        provide: {
            repositoryFactory: {
                create: () => {
                    return {
                        get: () => Promise.resolve(),
                        search: () => Promise.resolve(getVariants())
                    };
                }
            },
            acl: {
                can: () => true
            }
        },
        stubs: {
            'sw-modal': true,
            'sw-label': true,
            'sw-simple-search-field': true,
            'sw-empty-state': true,
            'sw-button': true
        }
    });
}

describe('module/sw-product/component/sw-product-variant-modal', () => {
    let wrapper;

    beforeEach(() => {
        wrapper = createWrapper();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should sort options by their position', () => {
        const sortedOptions = wrapper.vm.sortOptions(getOptions());

        expect(sortedOptions).toEqual([
            { name: 'a', translated: { name: 'a' }, group: { translated: { name: 'material' } }, position: 1 },
            { name: 'b', translated: { name: 'b' }, group: { translated: { name: 'color' } }, position: 1 },
            { name: 'c', translated: { name: 'c' }, group: { translated: { name: 'size' } }, position: 5 }
        ]);
    });

    it('should build variants options', () => {
        const builtVariantOptions = wrapper.vm.buildVariantOptions(getVariants());

        expect(builtVariantOptions).toBe('(material: a, color: b, size: c)');
    });

    it('should variant name', () => {
        const builtVariantName = wrapper.vm.buildVariantName(getVariants());

        expect(builtVariantName).toBe('random product (material: a, color: b, size: c)');
    });

    it('should ommit the paranthesis', () => {
        const builtVariantOptions = wrapper.vm.buildVariantOptions(getVariants(), ', ', true);

        expect(builtVariantOptions).toBe('material: a, color: b, size: c');
    });

    it('should use a custom seperator', () => {
        const builtVariantOptions = wrapper.vm.buildVariantOptions(getVariants(), ' - ');

        expect(builtVariantOptions).toBe('(material: a - color: b - size: c)');
    });

    it('should ommit the group name', () => {
        const builtVariantOptions = wrapper.vm.buildVariantOptions(getVariants(), ', ', false, true);

        expect(builtVariantOptions).toBe('(a, b, c)');
    });

    it('should get variant price of variant', () => {
        const variantPriceObject = wrapper.vm.getVariantPrice(getVariants());
        const netPrice = variantPriceObject.net;
        const grossPrice = variantPriceObject.gross;

        expect(netPrice).toBe(24);
        expect(grossPrice).toBe(24);
    });

    it('should get variant price of parent product', () => {
        const variantPriceObject = wrapper.vm.getVariantPrice(getVariants(false));
        const netPrice = variantPriceObject.net;
        const grossPrice = variantPriceObject.gross;

        expect(netPrice).toBe(12);
        expect(grossPrice).toBe(12);
    });

    it('should return the correct permissions tooltip', () => {
        const tooltipObject = wrapper.vm.getNoPermissionsTooltip('product.editor');

        expect(tooltipObject).toEqual({
            showDelay: 300,
            message: 'sw-privileges.tooltip.warning',
            appearance: 'dark',
            showOnDisabledElements: true,
            disabled: true
        });
    });
});
