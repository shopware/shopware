import { shallowMount, createLocalVue } from '@vue/test-utils_v2';
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
import 'src/app/component/base/sw-icon';

const fixture = [
    { id: utils.createId(), name: 'first entry', variation: [{ group: 'Size', option: 'M' }] },
];

const propertyFixture = [
    {
        id: utils.createId(),
        name: 'first entry',
        group: {
            name: 'example',
        },
    },
    {
        id: utils.createId(),
        name: 'second entry',
        group: {
            name: 'example',
        },
    },
    {
        id: utils.createId(),
        name: 'third',
        group: {
            name: 'entry',
        },
    },
];

function getCollection() {
    return new EntityCollection(
        '/test-entity',
        'testEntity',
        null,
        { isShopwareContext: true },
        fixture,
        fixture.length,
        null,
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
        null,
    );
}

const createEntityMultiSelect = async (customOptions) => {
    const localVue = createLocalVue();
    localVue.directive('popover', {});
    localVue.directive('tooltip', {});

    const options = {
        localVue,
        stubs: {
            'sw-select-base': await Shopware.Component.build('sw-select-base'),
            'sw-block-field': await Shopware.Component.build('sw-block-field'),
            'sw-base-field': await Shopware.Component.build('sw-base-field'),
            'sw-icon': await Shopware.Component.build('sw-icon'),
            'sw-select-selection-list': await Shopware.Component.build('sw-select-selection-list'),
            'sw-field-error': await Shopware.Component.build('sw-field-error'),
            'sw-loader': await Shopware.Component.build('sw-loader'),
            'sw-select-result-list': await Shopware.Component.build('sw-select-result-list'),
            'sw-popover': await Shopware.Component.build('sw-popover'),
            'sw-select-result': await Shopware.Component.build('sw-select-result'),
            'sw-highlight-text': await Shopware.Component.build('sw-highlight-text'),
            'sw-product-variant-info': await Shopware.Component.build('sw-product-variant-info'),
            'sw-label': true,
        },
        propsData: {
            entity: 'test',
            entityCollection: getCollection(),
            showClearableButton: true,
        },
        provide: {
            repositoryFactory: {
                create: () => {
                    return {
                        get: (value) => Promise.resolve({ id: value, name: value }),
                        search: () => Promise.resolve(getCollection()),
                    };
                },
            },
        },
    };

    return shallowMount(await Shopware.Component.build('sw-entity-multi-select'), {
        ...options,
        ...customOptions,
    });
};

describe('components/sw-entity-multi-select', () => {
    it('should be a Vue.js component', async () => {
        const swEntityMultiSelect = await createEntityMultiSelect();

        expect(swEntityMultiSelect.vm).toBeTruthy();
    });

    it('should emit the correct search term', async () => {
        const swEntityMultiSelect = await createEntityMultiSelect({
            propsData: {
                entity: 'property_group_option',
                entityCollection: getPropertyCollection(),
            },
            provide: {
                repositoryFactory: {
                    create: () => {
                        return {
                            search: () => Promise.resolve(getPropertyCollection()),
                        };
                    },
                },
            },
        });

        await swEntityMultiSelect.find('.sw-select__selection').trigger('click');
        await swEntityMultiSelect.find('input').setValue('first');
        await swEntityMultiSelect.find('input').trigger('change');
        await flushPromises();

        expect(swEntityMultiSelect.emitted('search-term-change')[0]).toEqual(['first']);
    });

    it('should not display variations', async () => {
        const swEntityMultiSelect = await createEntityMultiSelect();
        const productVariantInfo = swEntityMultiSelect.find('.sw-product-variant-info');

        expect(productVariantInfo.exists()).toBe(false);
    });

    it('should display variations', async () => {
        const swEntityMultiSelect = await createEntityMultiSelect({
            propsData: {
                value: fixture[0].id,
                entity: 'test',
                entityCollection: getCollection(),
                displayVariants: true,
            },
        });

        const productVariantInfo = swEntityMultiSelect.find('.sw-product-variant-info');

        expect(productVariantInfo.exists()).toBe(true);

        expect(productVariantInfo.find('.sw-product-variant-info__product-name').text())
            .toContain(fixture[0].name);

        expect(productVariantInfo.find('.sw-product-variant-info__specification').text())
            .toContain(fixture[0].variation[0].group);

        expect(productVariantInfo.find('.sw-product-variant-info__specification').text())
            .toContain(fixture[0].variation[0].option);
    });

    it('should show description line in results list', async () => {
        const swEntityMultiSelect = await createEntityMultiSelect({
            scopedSlots: {
                'result-label-property': `<template>
                        {{ props.item.name }}
                    </template>`,
                'result-description-property': `<template>
                        {{ props.item.group.name }}
                    </template>`,
            },
            propsData: {
                entity: 'property_group_option',
                entityCollection: getPropertyCollection(),
            },
            provide: {
                repositoryFactory: {
                    create: () => {
                        return {
                            search: () => Promise.resolve(getPropertyCollection()),
                        };
                    },
                },
            },
        });

        await swEntityMultiSelect.find('.sw-select__selection').trigger('click');
        await swEntityMultiSelect.find('input').trigger('change');
        await flushPromises();

        const firstListEntry = swEntityMultiSelect.findAll('.sw-select-result-list__item-list li').at(0);

        expect(firstListEntry.find('.sw-select-result').classes()).toContain('has--description');
        expect(firstListEntry.find('.sw-select-result__result-item-text').text()).toBe('first entry');
        expect(firstListEntry.find('.sw-select-result__result-item-description').text()).toBe('example');
    });

    it('should render select indicator', async () => {
        const swEntityMultiSelect = await createEntityMultiSelect({
            propsData: {
                entity: 'test',
                entityCollection: new EntityCollection(
                    '/property-group-option',
                    'property_group_option',
                    null,
                    { isShopwareContext: true },
                    [getPropertyCollection().at(0)],
                    1,
                    null,
                ),
            },
            provide: {
                repositoryFactory: {
                    create: () => {
                        return {
                            search: () => Promise.resolve(getPropertyCollection()),
                        };
                    },
                },
            },
        });

        await swEntityMultiSelect.find('.sw-select__selection').trigger('click');
        await swEntityMultiSelect.find('input').trigger('change');
        await flushPromises();

        expect(swEntityMultiSelect.find('.sw-select-result-list__item-list li .sw-icon').exists()).toBe(true);
    });

    it('should be possible to clear the selection', async () => {
        const wrapper = await createEntityMultiSelect();

        await wrapper.find('.sw-select__selection').trigger('click');
        await wrapper.find('input').trigger('change');
        await flushPromises();

        await wrapper.find('.sw-select__select-indicator-clear').trigger('click');
        expect(wrapper.emitted('change')[0][0].total).toBeNull();
    });
});
