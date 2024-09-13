/**
 * @package admin
 */

import { mount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';
import utils from 'src/core/service/util.service';

const fixture = [
    {
        id: utils.createId(),
        name: 'first entry',
        variation: [{ group: 'Size', option: 'M' }],
        active: true,
    },
    {
        id: utils.createId(),
        name: 'second entry',
        active: false,
    },
    {
        id: utils.createId(),
        name: 'third entry',
        active: true,
    },
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

async function createEntitySingleSelect(customOptions = {
    global: {},
    props: {},
    slots: {},
}) {
    const options = {
        global: {
            stubs: {
                'sw-select-base': await wrapTestComponent('sw-select-base'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-icon': {
                    template: '<div @click="$emit(\'click\', $event)"></div>',
                    props: ['size', 'color', 'name'],
                },
                'sw-field-error': await wrapTestComponent('sw-field-error'),
                'sw-select-result-list': await wrapTestComponent('sw-select-result-list', {
                    sync: true,
                }),
                'sw-select-result': await wrapTestComponent('sw-select-result'),
                'sw-highlight-text': await wrapTestComponent('sw-highlight-text', {
                    sync: true,
                }),
                'sw-loader': await wrapTestComponent('sw-loader'),
                'sw-product-variant-info': await wrapTestComponent('sw-product-variant-info'),
                'sw-inheritance-switch': true,
                'sw-ai-copilot-badge': true,
                'sw-help-text': true,
                'mt-loader': true,
                'sw-loader-deprecated': true,
                'sw-popover': {
                    template: '<div><slot></slot></div>',
                },
            },
            provide: {
                repositoryFactory: {
                    create: () => {
                        return {
                            get: (value) => Promise.resolve({ id: value, name: value }),
                            search: () => Promise.resolve([]),
                        };
                    },
                },
            },
            ...customOptions.global,
        },
        props: {
            value: null,
            entity: 'test',
            ...customOptions.props,
        },
        slots: {
            ...customOptions.slots,
        },
    };

    return mount(await wrapTestComponent('sw-entity-single-select', {
        sync: true,
    }), {
        ...options,
    });
}

describe('components/sw-entity-single-select', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createEntitySingleSelect();
        await flushPromises();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should have no reset option when it is not defined', async () => {
        const swEntitySingleSelect = await createEntitySingleSelect({
            props: {
                value: null,
                entity: 'test',
            },
        });
        await flushPromises();

        const { singleSelection } = swEntitySingleSelect.vm;

        expect(singleSelection).toBeNull();
    });

    it('should have disabled state results according to function', async () => {
        const wrapper = await createEntitySingleSelect({
            props: {
                value: null,
                entity: 'test',
                selectionDisablingMethod: item => item.name === 'second entry',
            },
            global: {
                provide: {
                    repositoryFactory: {
                        create: () => {
                            return {
                                search: () => Promise.resolve(getCollection()),
                            };
                        },
                    },
                },
            },
        });
        await flushPromises();

        await wrapper.find('input').trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-select-option--0').classes()).not.toContain('is--disabled');
        expect(wrapper.find('.sw-select-option--1').classes()).toContain('is--disabled');
        expect(wrapper.find('.sw-select-option--2').classes()).not.toContain('is--disabled');
    });

    it('should have no tooltip and enabled results with no disabling function', async () => {
        const wrapper = await createEntitySingleSelect({
            props: {
                value: null,
                entity: 'test',
            },
            global: {
                provide: {
                    repositoryFactory: {
                        create: () => {
                            return {
                                search: () => Promise.resolve(getCollection()),
                            };
                        },
                    },
                },
            },
        });
        await flushPromises();

        await wrapper.find('input').trigger('click');
        await flushPromises();

        const firstEntry = wrapper.find('.sw-select-option--0');
        expect(firstEntry.attributes('tooltip-mock-message')).toBeFalsy();
        expect(firstEntry.attributes('tooltip-mock-disabled')).toBe('true');
        expect(wrapper.find('.sw-select-option--0').classes()).not.toContain('is--disabled');
    });

    it('should show disabled selection tooltip when appropriate', async () => {
        const wrapper = await createEntitySingleSelect({
            props: {
                value: null,
                entity: 'test',
                selectionDisablingMethod: item => item.name === 'second entry',
                disabledSelectionTooltip: { message: 'test message' },
            },
            global: {
                provide: {
                    repositoryFactory: {
                        create: () => {
                            return {
                                search: () => Promise.resolve(getCollection()),
                            };
                        },
                    },
                },
            },
        });
        await flushPromises();

        await wrapper.find('input').trigger('click');
        await flushPromises();

        const firstEntry = wrapper.find('.sw-select-option--0');
        expect(firstEntry.attributes('tooltip-mock-message')).toBe('test message');
        expect(firstEntry.attributes('tooltip-mock-disabled')).toBe('true');
        const secondEntry = wrapper.find('.sw-select-option--1');
        expect(secondEntry.attributes('tooltip-mock-message')).toBe('test message');
        expect(secondEntry.attributes('tooltip-mock-disabled')).toBe('false');
    });

    it('should show active state of options if enabled', async () => {
        const wrapper = await createEntitySingleSelect({
            props: {
                value: null,
                entity: 'test',
                shouldShowActiveState: false,
            },
            global: {
                provide: {
                    repositoryFactory: {
                        create: () => {
                            return {
                                search: () => Promise.resolve(getCollection()),
                            };
                        },
                    },
                },
            },
        });
        await flushPromises();

        await wrapper.find('input').trigger('click');
        await flushPromises();

        let activeStateIcons = wrapper.findAll('.sw-entity-single-select__selection-active');

        expect(activeStateIcons).toHaveLength(0);

        await wrapper.setProps({
            shouldShowActiveState: true,
        });
        await flushPromises();

        activeStateIcons = wrapper.findAllComponents('.sw-entity-single-select__selection-active');

        const activeIconProps = {
            color: '#37d046',
            name: 'default-basic-shape-circle-filled',
            size: '6',
        };

        const inActiveIconProps = {
            color: '#d1d9e0',
            name: 'default-basic-shape-circle-filled',
            size: '6',
        };

        expect(activeStateIcons).toHaveLength(3);
        expect(activeStateIcons.at(0).props()).toStrictEqual(activeIconProps);
        expect(activeStateIcons.at(1).props()).toStrictEqual(inActiveIconProps);
        expect(activeStateIcons.at(2).props()).toStrictEqual(activeIconProps);

        await wrapper.setProps({
            shouldShowActiveState: false,
        });
        await flushPromises();

        activeStateIcons = wrapper.findAll('.sw-select-option .sw-entity-single-select__selection-active');

        expect(activeStateIcons).toHaveLength(0);
    });

    it('should have a reset option when it is defined an the value is null', async () => {
        const swEntitySingleSelect = await createEntitySingleSelect({
            props: {
                value: null,
                entity: 'test',
                resetOption: 'reset',
            },
        });
        await flushPromises();

        const { singleSelection } = swEntitySingleSelect.vm;

        expect(singleSelection).not.toBeNull();
        expect(singleSelection.id).toBeNull();
        expect(singleSelection.name).toBe('reset');
    });

    it('should have no reset option when it is defined but the value is not null', async () => {
        const swEntitySingleSelect = await createEntitySingleSelect({
            props: {
                value: 'uuid',
                entity: 'test',
                resetOption: 'reset',
            },
        });
        await flushPromises();

        await swEntitySingleSelect.vm.$nextTick();

        const { singleSelection } = swEntitySingleSelect.vm;

        expect(singleSelection).not.toBeNull();
        expect(singleSelection.id).toBe('uuid');
        expect(singleSelection.name).toBe('uuid');
    });

    it('should have prepend reset option to resultCollection when resetOption is given', async () => {
        const swEntitySingleSelect = await createEntitySingleSelect({
            props: {
                value: '',
                entity: 'test',
                resetOption: 'reset',
            },
            global: {
                provide: {
                    repositoryFactory: {
                        create: () => {
                            return {
                                search: () => Promise.resolve(getCollection()),
                            };
                        },
                    },
                },
            },
        });
        await flushPromises();

        swEntitySingleSelect.vm.loadData();
        await swEntitySingleSelect.vm.$nextTick();

        const { resultCollection } = swEntitySingleSelect.vm;

        expect(resultCollection).toHaveLength(getCollection().length + 1);
        expect(resultCollection[0].name).toBe('reset');
    });

    it('should not show the selected item on first entry', async () => {
        const secondItemId = `${fixture[2].id}`;

        const wrapper = await createEntitySingleSelect({
            props: {
                value: secondItemId,
                entity: 'test',
                resetOption: 'reset',
            },
            global: {
                provide: {
                    repositoryFactory: {
                        create: () => {
                            return {
                                search: () => Promise.resolve(getCollection()),
                                get: (id) => {
                                    if (id === secondItemId) {
                                        return Promise.resolve(fixture[2]);
                                    }

                                    return Promise.reject();
                                },
                            };
                        },
                    },
                },
            },
        });
        await flushPromises();

        await wrapper.find('input').trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-select-option--0').text()).toBe('reset');
        expect(wrapper.find('.sw-select-option--1').text()).toBe('first entry');
        expect(wrapper.find('.sw-select-option--2').text()).toBe('second entry');
        expect(wrapper.find('.sw-select-option--3').text()).toBe('third entry');
    });

    it('should not emit the paginate event when user does not scroll to the end of list', async () => {
        const wrapper = await createEntitySingleSelect({
            props: {
                value: '',
                entity: 'test',
                resetOption: 'reset',
            },
            global: {
                provide: {
                    repositoryFactory: {
                        create: () => {
                            return {
                                search: () => Promise.resolve(getCollection()),
                            };
                        },
                    },
                },
            },
        });
        await flushPromises();

        await wrapper.find('input').trigger('click');
        await flushPromises();

        const selectResultList = wrapper.findComponent({
            ref: 'resultsList',
        });
        const listContent = wrapper.find('.sw-select-result-list__content');

        Object.defineProperty(listContent.element, 'scrollHeight', { value: 1050 });
        Object.defineProperty(listContent.element, 'clientHeight', { value: 250 });
        Object.defineProperty(listContent.element, 'scrollTop', { value: 150 });

        await listContent.trigger('scroll');

        expect(selectResultList.emitted('paginate')).toBeUndefined();
    });

    it('should emit the paginate event when user scroll to the end of list', async () => {
        const wrapper = await createEntitySingleSelect({
            props: {
                value: '',
                entity: 'test',
                resetOption: 'reset',
            },
            global: {
                provide: {
                    repositoryFactory: {
                        create: () => {
                            return {
                                search: () => Promise.resolve(getCollection()),
                            };
                        },
                    },
                },
            },
        });
        await flushPromises();

        await wrapper.find('input').trigger('click');
        await flushPromises();

        const selectResultList = wrapper.findComponent({
            ref: 'resultsList',
        });
        const listContent = wrapper.find('.sw-select-result-list__content');

        Object.defineProperty(listContent.element, 'scrollHeight', { value: 1050 });
        Object.defineProperty(listContent.element, 'clientHeight', { value: 250 });
        Object.defineProperty(listContent.element, 'scrollTop', { value: 800 });

        await listContent.trigger('scroll');

        expect(selectResultList.emitted('paginate')).toBeDefined();
        expect(selectResultList.emitted('paginate')).toHaveLength(1);
        expect(selectResultList.emitted('paginate')[0]).toEqual([]);
    });

    it('should emit the correct search term', async () => {
        const swEntitySingleSelect = await createEntitySingleSelect({
            props: {
                value: null,
                entity: 'property_group_option',
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
        await flushPromises();

        swEntitySingleSelect.vm.loadData();
        await swEntitySingleSelect.vm.$nextTick();
        await swEntitySingleSelect.vm.$nextTick();

        await swEntitySingleSelect.find('.sw-select__selection').trigger('click');
        await swEntitySingleSelect.find('input').setValue('first');
        await swEntitySingleSelect.find('input').trigger('change');
        await swEntitySingleSelect.vm.$nextTick();

        expect(swEntitySingleSelect.emitted('search-term-change')[0]).toEqual(['first']);
    });

    it('should not display variations', async () => {
        const swEntitySingleSelect = await createEntitySingleSelect();
        await flushPromises();
        const productVariantInfo = swEntitySingleSelect.find('.sw-product-variant-info');

        expect(productVariantInfo.exists()).toBeFalsy();
    });

    it('should display variations', async () => {
        const swEntitySingleSelect = await createEntitySingleSelect({
            props: {
                value: fixture[0].id,
                entity: 'test',
                displayVariants: true,
                resetOption: 'reset',
            },
            global: {
                provide: {
                    repositoryFactory: {
                        create: () => {
                            return {
                                get: () => Promise.resolve(fixture[0]),
                            };
                        },
                    },
                },
            },
        });
        await flushPromises();

        await swEntitySingleSelect.vm.loadSelected();

        await swEntitySingleSelect.vm.$nextTick(() => {
            const productVariantInfo = swEntitySingleSelect.find('.sw-product-variant-info');

            expect(productVariantInfo.exists()).toBeTruthy();

            expect(productVariantInfo.find('.sw-product-variant-info__product-name').text())
                .toEqual(fixture[0].name);

            expect(productVariantInfo.find('.sw-product-variant-info__specification').text())
                .toContain(fixture[0].variation[0].group);

            expect(productVariantInfo.find('.sw-product-variant-info__specification').text())
                .toContain(fixture[0].variation[0].option);
        });
    });

    it('should display label provided by callback', async () => {
        const swEntitySingleSelect = await createEntitySingleSelect({
            props: {
                value: fixture[0].id,
                entity: 'test',
                labelCallback: () => 'test',
            },
            global: {
                provide: {
                    repositoryFactory: {
                        create: () => {
                            return {
                                get: () => Promise.resolve(fixture[0]),
                                search: () => Promise.resolve(getCollection()),
                            };
                        },
                    },
                },
            },
        });
        await flushPromises();

        await swEntitySingleSelect.vm.$nextTick();
        expect(swEntitySingleSelect.find('.sw-entity-single-select__selection-text').text())
            .toBe('test');

        await swEntitySingleSelect.find('input').trigger('click');
        await swEntitySingleSelect.vm.$nextTick();

        expect(swEntitySingleSelect.find('input').element.value).toBe('test');
        expect(swEntitySingleSelect.find('.sw-select-result__result-item-text').text()).toBe('test');
    });

    it('should show the clearable icon in the single select', async () => {
        const wrapper = await createEntitySingleSelect({
            props: {
                showClearableButton: true,
            },
        });
        await flushPromises();

        const clearableIcon = wrapper.find('.sw-select__select-indicator-clear');
        expect(clearableIcon.isVisible()).toBe(true);
    });

    it('should clear the selection when clicking on clear icon', async () => {
        const wrapper = await createEntitySingleSelect({
            props: {
                value: fixture[0].id,
                entity: 'test',
                labelCallback: () => 'test',
                showClearableButton: true,
            },
            global: {
                provide: {
                    repositoryFactory: {
                        create: () => {
                            return {
                                get: () => Promise.resolve(fixture[0]),
                                search: () => Promise.resolve(getCollection()),
                            };
                        },
                    },
                },
            },
        });

        // wait until fetched data gets rendered
        await flushPromises();

        // expect test value selected
        let selectionText = wrapper.find('.sw-entity-single-select__selection-text');
        expect(selectionText.text())
            .toBe('test');

        // expect no emitted value
        expect(wrapper.emitted('change')).toBeUndefined();

        // click on clear
        const clearableIcon = wrapper.find('.sw-select__select-indicator-clear');
        await clearableIcon.trigger('click');

        // expect emitting resetting value
        const emittedChangeValue = wrapper.emitted('update:value')[0];
        expect(emittedChangeValue).toEqual([null]);

        // emulate v-model change
        await wrapper.setProps({
            value: emittedChangeValue[0],
        });

        // expect empty selection
        selectionText = wrapper.find('.sw-entity-single-select__selection-text');
        expect(selectionText.text()).toBe('');
    });

    it('should show description line in results list', async () => {
        const wrapper = await createEntitySingleSelect({
            slots: {
                'result-label-property': `<span>
                        {{ params.item.name }}
                    </span>`,
                'result-description-property': `<span>
                        {{ params.item.group.name }}
                    </span>`,
            },
            props: {
                value: null,
                entity: 'property_group_option',
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
        await flushPromises();

        wrapper.vm.loadData();
        await flushPromises();

        await wrapper.find('.sw-select__selection').trigger('click');
        await wrapper.find('input').trigger('change');
        await flushPromises();

        const firstListEntry = wrapper.findAll('.sw-select-result-list__item-list li').at(0);

        expect(firstListEntry.classes()).toContain('has--description');
        expect(firstListEntry.find('.sw-select-result__result-item-text').text()).toBe('first entry');
        expect(firstListEntry.find('.sw-select-result__result-item-description').text()).toBe('example');
    });

    it('should recognize non-existing entity and offer entity creation', async () => {
        const nonExistingEntityMock = new EntityCollection(
            '',
            '',
            Shopware.Context.api,
            null,
            [],
            0,
        );

        const existingEntityMock = new EntityCollection(
            '',
            '',
            Shopware.Context.api,
            null,
            [
                {
                    id: '12345asd',
                },
            ],
            1,
        );

        const swOriginEntitySingleSelect = await wrapTestComponent('sw-entity-single-select', {
            sync: true,
        });
        const wrapper = mount(swOriginEntitySingleSelect, {
            props: {
                value: 'asdf555',
                entity: 'product_manufacturer',
                allowEntityCreation: true,
            },
            global: {
                stubs: {
                    'sw-select-base': await wrapTestComponent('sw-select-base'),
                    'sw-block-field': await wrapTestComponent('sw-block-field'),
                    'sw-base-field': await wrapTestComponent('sw-base-field'),
                    'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
                    'sw-highlight-text': await wrapTestComponent('sw-highlight-text', {
                        sync: true,
                    }),
                    'sw-field-error': true,
                    'sw-loader': true,
                    'sw-icon': true,
                    'sw-product-variant-info': true,
                    'sw-select-result': {
                        template: '<div><slot></slot></div>',
                    },
                    'sw-inheritance-switch': true,
                    'sw-ai-copilot-badge': true,
                    'sw-help-text': true,
                    'sw-popover': {
                        template: '<div><slot></slot></div>',
                    },
                },
                provide: {
                    repositoryFactory: {
                        create: () => ({
                            search: (context) => {
                                // Should return no manufacturer when component searches for "Cars"
                                if (context.term === 'Cars') {
                                    return Promise.resolve(nonExistingEntityMock);
                                }

                                // Should return one manufacturer when component searches for "Bikes"
                                if (context.term === 'Bikes') {
                                    return Promise.resolve(existingEntityMock);
                                }

                                return Promise.resolve(new EntityCollection(
                                    '',
                                    '',
                                    Shopware.Context.api,
                                    null,
                                    [],
                                    0,
                                ));
                            },
                            get: () => Promise.resolve({
                                id: 'manufacturerId',
                                name: 'ThisIsMyEntity',
                                product: [],
                            }),
                            create: () => Promise.resolve({}),
                        }),
                    },
                },
            },
        });
        await flushPromises();

        const displaySearchSpy = jest.spyOn(wrapper.vm, 'displaySearch');
        const input = wrapper.find('.sw-entity-single-select__selection-input');

        await wrapper.find('.sw-select__selection').trigger('click');

        // Enter a new search term
        await input.setValue('Cars');

        // Flush debouncedSearch from parent "sw-entity-single-select" component
        const select = swOriginEntitySingleSelect.methods.debouncedSearch;
        await select.flush();

        // Wait for rendering
        await flushPromises();
        // Ensure manufacturer does not exist
        expect(wrapper.vm.entityExists).toBe(false);

        // Ensure non-existing manufacturer is offered to be created by a new select result item
        expect(wrapper.vm.newEntityName).toBe('Cars');
        expect(displaySearchSpy).toHaveBeenCalled();

        await flushPromises();
        const resultItem = wrapper.find('.sw-select-result-list__item-list').findComponent('.sw-highlight-text');

        expect(resultItem.text()).toBe('global.sw-single-select.labelEntityAdd');
        expect(resultItem.props().searchTerm).toBe('Cars');
    });
});
