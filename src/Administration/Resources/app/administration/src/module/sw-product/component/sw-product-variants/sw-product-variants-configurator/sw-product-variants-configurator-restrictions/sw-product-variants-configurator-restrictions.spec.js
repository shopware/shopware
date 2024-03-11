/*
 * @package inventory
 */

import { mount } from '@vue/test-utils';

describe('components/base/sw-product-variants-configurator-restrictions', () => {
    async function createWrapper() {
        return mount(await wrapTestComponent('sw-product-variants-configurator-restrictions', { sync: true }), {
            props: {
                product: {
                    configuratorSettings: [
                        {
                            optionId: 'option1',
                            option: {
                                id: 'option1',
                                groupId: 'group1',
                                name: 'Red',
                                group: {
                                    id: 'color',
                                    name: 'color',
                                },
                                translated: {
                                    name: 'option1',
                                },
                            },
                            isDeleted: false,
                        },
                        {
                            optionId: 'option2',
                            option: {
                                id: 'option2',
                                groupId: 'shoeSize',
                                name: '45',
                                group: {
                                    id: 'shoeSize',
                                    name: '45',
                                },
                                translated: {
                                    name: '45',
                                },
                            },
                            isDeleted: false,
                        },
                    ],
                    variantRestrictions: [
                        {
                            id: 'restriction1',
                            values: [
                                {
                                    id: 'value1',
                                    group: 'group1',
                                    options: ['option1', 'option2'],
                                },
                            ],
                            translated:
                                {
                                    name: 'test',
                                },
                        },
                        {
                            id: 'restriction2WithNoOptions',
                            values: [
                                {
                                    id: 'value1',
                                    group: 'group1',
                                    options: [],
                                },
                            ],
                            translated:
                                {
                                    name: 'test',
                                },
                        },
                    ],
                },
                selectedGroups: [{
                    id: 'group1',
                    options: ['option1', 'option2'],
                    translated: {
                        name: 'group1',
                    },
                }],
            },
            global: {
                provide: {
                    repositoryFactory: {
                        create: () => ({
                            search: () => Promise.resolve(),
                            create: () => Promise.resolve(),
                        }),
                    },
                    validationService: {},
                },
                stubs: {
                    'sw-simple-search-field': true,
                    'sw-loader': true,
                    'sw-icon': true,
                    'sw-popover': await wrapTestComponent('sw-popover'),
                    'sw-button': await wrapTestComponent('sw-button'),
                    'sw-data-grid': await wrapTestComponent('sw-data-grid'),
                    'sw-context-button': await wrapTestComponent('sw-context-button'),
                    'sw-context-menu': await wrapTestComponent('sw-context-menu'),
                    'sw-context-menu-item': await wrapTestComponent('sw-context-menu-item'),
                    'sw-product-restriction-selection': await wrapTestComponent('sw-product-restriction-selection'),
                    'sw-multi-select': await wrapTestComponent('sw-multi-select'),
                    'sw-select-selection-list': await wrapTestComponent('sw-select-selection-list'),
                    'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
                    'sw-select-result': await wrapTestComponent('sw-select-result'),
                    'sw-select-base': await wrapTestComponent('sw-select-base'),
                    'sw-block-field': await wrapTestComponent('sw-block-field'),
                    'sw-base-field': await wrapTestComponent('sw-base-field'),
                },
            },
        });
    }

    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
        await flushPromises();
    });

    it('should get restrictions with naming', () => {
        const restriction = wrapper.vm.getRestrictionsWithNaming('restriction1');
        expect(restriction).toEqual({
            id: 'restriction1',
            values: [
                {
                    group: 'group1',
                    options: ['option1', '45'],
                },
            ],
        });
    });

    it('should get options for groupId', async () => {
        const options = await wrapper.vm.getOptionsForGroupId('group1');

        expect(options).toEqual([{
            isDeleted: false,
            option: {
                groupId: 'group1',
                id: 'option1',
                name: 'Red',
                group: {
                    id: 'color',
                    name: 'color',
                },
                translated: {
                    name: 'option1',
                },
            },
            optionId: 'option1',
        }]);

        expect(options).not.toEqual([{
            isDeleted: false,
            option: {
                id: 'option2',
                groupId: 'shoeSize',
                name: '45',
                group: {
                    id: 'shoeSize',
                    name: '45',
                },
                translated: {
                    name: '45',
                },
            },
            optionId: 'option2',
        }]);
    });

    it('should test filterEmptyValues', async () => {
        await wrapper.vm.filterEmptyValues();
        expect(wrapper.vm.product.variantRestrictions).toEqual([{
            id: 'restriction1',
            values: [
                {
                    id: 'value1',
                    group: 'group1',
                    options: ['option1', 'option2'],
                },
            ],
            translated: {
                name: 'test',
            },
        }]);
    });

    it('should add an empty restriction combination', async () => {
        await wrapper.find('.sw-button').trigger('click');
        expect(wrapper.vm.actualRestriction).toEqual({
            id: expect.any(String),
            values: [{
                id: expect.any(String),
                group: 'group1',
                options: [],
            }],
        });
    });

    it('should test cancelAddRestriction', async () => {
        await wrapper.find('.sw-button').trigger('click');
        await flushPromises();

        await wrapper.findAll('.sw-button').at(2).trigger('click');
        await flushPromises();

        expect(wrapper.vm.actualRestriction).toEqual({});
    });

    it('should test method addEmptyRestriction', async () => {
        await wrapper.find('.sw-button').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-product-variants-configurator-restrictions__button-new-restriction').trigger('click');
        await flushPromises();

        expect(wrapper.vm.actualRestriction).toEqual({
            id: expect.any(String),
            values: [{
                id: expect.any(String),
                group: 'group1',
                options: [],
            },
            {
                id: expect.any(String),
                group: 'group1',
                options: [],
            }],
        });
    });


    it('should edit a restriction combination', async () => {
        const contextButton = wrapper.find('.sw-context-button');
        await contextButton.trigger('click');
        await flushPromises();
        expect(wrapper.find('.sw-context-menu').exists()).toBe(true);

        const contextMenuItem = wrapper.findAllComponents('.sw-context-menu-item');
        await contextMenuItem.at(0).trigger('click');

        expect(wrapper.find('.sw-context-menu').exists()).toBe(false);
        expect(wrapper.vm.actualRestriction).toEqual({
            id: 'restriction1',
            values: [
                {
                    id: 'value1',
                    group: 'group1',
                    options: ['option1', 'option2'],
                },
            ],
            translated: {
                name: 'test',
            },
        });
    });

    it('should delete a restriction combination and expect an empty array', async () => {
        await wrapper.find('.sw-context-button').trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-context-menu').exists()).toBe(true);
        const contextMenuItem = wrapper.findAllComponents('.sw-context-menu-item');

        await contextMenuItem.at(1).trigger('click');
        expect(wrapper.find('.sw-context-menu').exists()).toBe(false);
        expect(wrapper.vm.product.variantRestrictions).toEqual([]);
    });

    it('should test saveAddRestriction', async () => {
        await wrapper.setProps({
            product: {
                variantRestrictions: null,
            },
        });

        await wrapper.vm.addEmptyRestrictionCombination();
        await wrapper.vm.saveAddRestriction();

        expect(wrapper.vm.product.variantRestrictions).toEqual([{
            id: expect.any(String),
            values: [{
                id: expect.any(String),
                group: 'group1',
                options: [],
            }],
        }]);
    });

    it('should save restriction', async () => {
        await wrapper.find('.sw-button').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-select-selection-list').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-select-option--0').trigger('click');
        await wrapper.find('.sw-button--primary').trigger('click');

        expect(wrapper.vm.product.variantRestrictions).toEqual([
            {
                id: 'restriction1',
                translated: {
                    name: 'test',
                },
                values: [
                    {
                        group: 'group1',
                        id: 'value1',
                        options: [
                            'option1',
                            'option2',
                        ],
                    },
                ],
            },
            {
                id: expect.any(String),
                values: [
                    {
                        group: 'group1',
                        id: expect.any(String),
                        options: [
                            'option1',
                        ],
                    },
                ],
            },
        ]);
    });
});
