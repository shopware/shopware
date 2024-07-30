/**
 * @package admin
 * @group disabledCompat
 */

import { mount } from '@vue/test-utils';

import EntityCollection from 'src/core/data/entity-collection.data';

const fixture = [
    { id: 'ae12b3c2-8236-4eb2-84a1-b933863a7905', name: 'first entry', variation: [{ group: 'Size', option: 'M' }] },
];

const propertyFixture = [
    {
        id: '46a40e8d-671b-4c91-b0c7-cecee1bdea4a',
        name: 'first entry',
        group: {
            name: 'example',
        },
    },
    {
        id: 'c8637a67-cf98-4533-ac42-48513b7cb96f',
        name: 'second entry',
        group: {
            name: 'example',
        },
    },
    {
        id: '4eed437b-b242-418e-be58-b3fa3f2d15f9',
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

const createWrapper = async (customOptions = {}) => {
    const wrapper = mount(await wrapTestComponent('sw-entity-multi-select', { sync: true }), {
        global: {
            stubs: {
                'sw-select-base': await wrapTestComponent('sw-select-base'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-icon': await wrapTestComponent('sw-icon'),
                'sw-icon-deprecated': await wrapTestComponent('sw-icon-deprecated'),
                'sw-select-selection-list': await wrapTestComponent('sw-select-selection-list'),
                'sw-field-error': await wrapTestComponent('sw-field-error'),
                'sw-loader': await wrapTestComponent('sw-loader'),
                'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
                'sw-popover': await wrapTestComponent('sw-popover'),
                'sw-popover-deprecated': await wrapTestComponent('sw-popover-deprecated', { sync: true }),
                'sw-select-result': await wrapTestComponent('sw-select-result'),
                'sw-highlight-text': await wrapTestComponent('sw-highlight-text'),
                'sw-product-variant-info': await wrapTestComponent('sw-product-variant-info'),
                'sw-label': await wrapTestComponent('sw-label'),
                'sw-inheritance-switch': true,
                'sw-ai-copilot-badge': true,
                'sw-help-text': true,
                'sw-button': true,
                'mt-icon': true,
                'sw-color-badge': true,
                'mt-loader': true,
                'sw-loader-deprecated': true,
                'mt-floating-ui': true,
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
                ...customOptions.global?.provide,
            },
        },
        props: {
            entity: 'test',
            entityCollection: getCollection(),
            showClearableButton: true,
            ...customOptions.props,
        },
        slots: customOptions?.slots,
    });

    await flushPromises();

    return wrapper;
};

describe('components/sw-entity-multi-select', () => {
    it('should emit the correct search term', async () => {
        const swEntityMultiSelect = await createWrapper({
            props: {
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
        await flushPromises();

        await swEntityMultiSelect.find('input').setValue('first');
        await swEntityMultiSelect.find('input').trigger('change');
        await flushPromises();

        expect(swEntityMultiSelect.emitted('search-term-change')[0]).toEqual(['first']);
    });

    it('should not display variations', async () => {
        const swEntityMultiSelect = await createWrapper();
        const productVariantInfo = swEntityMultiSelect.find('.sw-product-variant-info');

        expect(productVariantInfo.exists()).toBe(false);
    });

    it('should display variations', async () => {
        const wrapper = await createWrapper({
            props: {
                value: fixture[0].id,
                entity: 'test',
                entityCollection: getCollection(),
                displayVariants: true,
            },
        });

        const productVariantInfo = wrapper.find('.sw-product-variant-info');
        expect(productVariantInfo.exists()).toBe(true);

        expect(productVariantInfo.find('.sw-product-variant-info__product-name').text())
            .toContain(fixture[0].name);

        expect(productVariantInfo.find('.sw-product-variant-info__specification').text())
            .toContain(fixture[0].variation[0].group);

        expect(productVariantInfo.find('.sw-product-variant-info__specification').text())
            .toContain(fixture[0].variation[0].option);
    });

    it('should show description line in results list', async () => {
        const wrapper = await createWrapper({
            slots: {
                'result-label-property': `<template>
                        {{ item.name }}
                    </template>`,
                'result-description-property': `<template>
                        {{ item.group.name }}
                    </template>`,
            },
            props: {
                entity: 'property_group_option',
                entityCollection: getPropertyCollection(),
            },
            global: {
                provide: {
                    repositoryFactory: {
                        create: () => {
                            return {
                                search: () => Promise.resolve(getPropertyCollection()),
                            };
                        },
                    },
                },
            },
        });

        await wrapper.find('.sw-select__selection').trigger('click');
        await wrapper.find('input').trigger('change');
        await flushPromises();

        const firstListEntry = wrapper.findAll('.sw-select-result-list__item-list li').at(0);

        expect(firstListEntry.classes()).toContain('has--description');
        expect(firstListEntry.find('.sw-select-result__result-item-text').text()).toBe('first entry');
        expect(firstListEntry.find('.sw-select-result__result-item-description').text()).toBe('example');
    });

    it('should render select indicator', async () => {
        const swEntityMultiSelect = await createWrapper({
            props: {
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
            global: {
                provide: {
                    repositoryFactory: {
                        create: () => {
                            return {
                                search: () => Promise.resolve(getPropertyCollection()),
                            };
                        },
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
        const wrapper = await createWrapper();

        await wrapper.find('.sw-select__selection').trigger('click');
        await wrapper.find('input').trigger('change');
        await flushPromises();

        await wrapper.find('.sw-select__select-indicator-clear').trigger('click');
        expect(wrapper.emitted('update:entityCollection')[0][0].total).toBeNull();
    });
});
