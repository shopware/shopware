import { shallowMount, createLocalVue } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';
import utils from 'src/core/service/util.service';
import 'src/app/component/form/select/entity/sw-entity-multi-select';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/field-base/sw-field-error';
import 'src/app/component/form/select/base/sw-select-selection-list';
import 'src/app/component/base/sw-label';
import 'src/app/component/utils/sw-loader';
import 'src/app/component/form/select/base/sw-select-result-list';
import 'src/app/component/utils/sw-popover';
import 'src/app/component/form/select/base/sw-select-result';
import 'src/app/component/base/sw-highlight-text';
import 'src/app/component/base/sw-product-variant-info';

const fixture = [
    { id: utils.createId(), name: 'first entry', variation: [{ group: 'Size', option: 'M' }] }
];

const propertyFixture = [
    {
        id: utils.createId(),
        name: 'first entry',
        group: {
            name: 'example'
        }
    },
    {
        id: utils.createId(),
        name: 'second entry',
        group: {
            name: 'example'
        }
    },
    {
        id: utils.createId(),
        name: 'third',
        group: {
            name: 'entry'
        }
    }
];

function getCollection() {
    return new EntityCollection(
        '/test-entity',
        'testEntity',
        null,
        { isShopwareContext: true },
        fixture,
        fixture.length,
        null
    );
}

function getPropertyCollection() {
    return new EntityCollection(
        '/property-group-option',
        'property_group_option',
        null,
        { isShopwareContext: true },
        propertyFixture,
        propertyFixture.length,
        null
    );
}

const createEntityMultiSelect = (customOptions) => {
    const localVue = createLocalVue();
    localVue.directive('popover', {});
    localVue.directive('tooltip', {});

    const options = {
        localVue,
        stubs: {
            'sw-select-base': Shopware.Component.build('sw-select-base'),
            'sw-block-field': Shopware.Component.build('sw-block-field'),
            'sw-base-field': Shopware.Component.build('sw-base-field'),
            'sw-icon': {
                template: '<div></div>'
            },
            'sw-select-selection-list': Shopware.Component.build('sw-select-selection-list'),
            'sw-field-error': Shopware.Component.build('sw-field-error'),
            'sw-label': true,
            'sw-loader': Shopware.Component.build('sw-loader'),
            'sw-select-result-list': Shopware.Component.build('sw-select-result-list'),
            'sw-popover': Shopware.Component.build('sw-popover'),
            'sw-select-result': Shopware.Component.build('sw-select-result'),
            'sw-highlight-text': Shopware.Component.build('sw-highlight-text'),
            'sw-product-variant-info': Shopware.Component.build('sw-product-variant-info')
        },
        propsData: {
            entity: 'test',
            entityCollection: getCollection()
        },
        provide: {
            repositoryFactory: {
                create: () => {
                    return {
                        get: (value) => Promise.resolve({ id: value, name: value }),
                        search: () => Promise.resolve(getCollection())
                    };
                }
            }
        }
    };

    return shallowMount(Shopware.Component.build('sw-entity-multi-select'), {
        ...options,
        ...customOptions
    });
};

describe('components/sw-entity-multi-select', () => {
    it('should be a Vue.js component', async () => {
        const swEntityMultiSelect = createEntityMultiSelect();

        expect(swEntityMultiSelect.vm).toBeTruthy();
    });

    it('should emit the correct search term', async () => {
        const swEntityMultiSelect = await createEntityMultiSelect({
            propsData: {
                entity: 'property_group_option',
                entityCollection: getPropertyCollection()
            },
            provide: {
                repositoryFactory: {
                    create: () => {
                        return {
                            search: () => Promise.resolve(getPropertyCollection())
                        };
                    }
                }
            }
        });

        swEntityMultiSelect.vm.loadData();
        await swEntityMultiSelect.vm.$nextTick();
        await swEntityMultiSelect.vm.$nextTick();

        await swEntityMultiSelect.find('.sw-select__selection').trigger('click');
        await swEntityMultiSelect.find('input').setValue('first');
        await swEntityMultiSelect.find('input').trigger('change');
        await swEntityMultiSelect.vm.$nextTick();

        expect(swEntityMultiSelect.emitted('search-term-change')[0]).toEqual(['first']);
    });

    it('should not display variations', () => {
        const swEntityMultiSelect = createEntityMultiSelect();
        const productVariantInfo = swEntityMultiSelect.find('.sw-product-variant-info');

        expect(productVariantInfo.exists()).toBeFalsy();
    });

    it('should display variations', () => {
        const swEntityMultiSelect = createEntityMultiSelect({
            propsData: {
                value: fixture[0].id,
                entity: 'test',
                entityCollection: getCollection(),
                displayVariants: true
            }
        });

        const productVariantInfo = swEntityMultiSelect.find('.sw-product-variant-info');

        expect(productVariantInfo.exists()).toBeTruthy();

        expect(productVariantInfo.find('.sw-product-variant-info__product-name').text())
            .toContain(fixture[0].name);

        expect(productVariantInfo.find('.sw-product-variant-info__specification').text())
            .toContain(fixture[0].variation[0].group);

        expect(productVariantInfo.find('.sw-product-variant-info__specification').text())
            .toContain(fixture[0].variation[0].option);
    });
});
