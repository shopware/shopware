import { shallowMount, createLocalVue } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';
import utils from 'src/core/service/util.service';
import 'src/app/component/form/select/entity/sw-entity-single-select';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/field-base/sw-field-error';
import 'src/app/component/form/select/base/sw-select-result-list';
import 'src/app/component/utils/sw-popover';
import 'src/app/component/form/select/base/sw-select-result';
import 'src/app/component/base/sw-highlight-text';
import 'src/app/component/utils/sw-loader';
import 'src/app/component/base/sw-product-variant-info';

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
    }
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

async function createEntitySingleSelect(customOptions) {
    const localVue = createLocalVue();
    localVue.directive('popover', {});
    localVue.directive('tooltip', {
        bind(el, binding) {
            el.setAttribute('tooltip-message', binding.value.message);
            el.setAttribute('tooltip-disabled', binding.value.disabled);
        },
        inserted(el, binding) {
            el.setAttribute('tooltip-message', binding.value.message);
            el.setAttribute('tooltip-disabled', binding.value.disabled);
        },
        update(el, binding) {
            el.setAttribute('tooltip-message', binding.value.message);
            el.setAttribute('tooltip-disabled', binding.value.disabled);
        }
    });

    const options = {
        localVue,
        stubs: {
            'sw-select-base': await Shopware.Component.build('sw-select-base'),
            'sw-block-field': await Shopware.Component.build('sw-block-field'),
            'sw-base-field': await Shopware.Component.build('sw-base-field'),
            'sw-icon': {
                template: '<div @click="$emit(\'click\', $event)"></div>',
                props: ['size', 'color', 'name']
            },
            'sw-field-error': await Shopware.Component.build('sw-field-error'),
            'sw-select-result-list': await Shopware.Component.build('sw-select-result-list'),
            'sw-popover': await Shopware.Component.build('sw-popover'),
            'sw-select-result': await Shopware.Component.build('sw-select-result'),
            'sw-highlight-text': await Shopware.Component.build('sw-highlight-text'),
            'sw-loader': await Shopware.Component.build('sw-loader'),
            'sw-product-variant-info': await Shopware.Component.build('sw-product-variant-info')
        },
        propsData: {
            value: null,
            entity: 'test'
        },
        provide: {
            repositoryFactory: {
                create: () => {
                    return {
                        get: (value) => Promise.resolve({ id: value, name: value })
                    };
                }
            }
        }
    };

    return shallowMount(await Shopware.Component.build('sw-entity-single-select'), {
        ...options,
        ...customOptions
    });
}

describe('components/sw-entity-single-select', () => {
    it('should be a Vue.js component', async () => {
        const swEntitySingleSelect = await createEntitySingleSelect();
        await flushPromises();

        expect(swEntitySingleSelect.vm).toBeTruthy();
    });

    it('should have no reset option when it is not defined', async () => {
        const swEntitySingleSelect = await createEntitySingleSelect({
            propsData: {
                value: null,
                entity: 'test'
            }
        });
        await flushPromises();

        const { singleSelection } = swEntitySingleSelect.vm;

        expect(singleSelection).toBeNull();
    });

    it('should have disabled state results according to function', async () => {
        const wrapper = await createEntitySingleSelect({
            propsData: {
                value: null,
                entity: 'test',
                selectionDisablingMethod: item => item.name === 'second entry'
            },
            provide: {
                repositoryFactory: {
                    create: () => {
                        return {
                            search: () => Promise.resolve(getCollection())
                        };
                    }
                }
            }
        });
        await flushPromises();

        await wrapper.find('input').trigger('click');
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-select-option--0').classes()).not.toContain('is--disabled');
        expect(wrapper.find('.sw-select-option--1').classes()).toContain('is--disabled');
        expect(wrapper.find('.sw-select-option--2').classes()).not.toContain('is--disabled');
    });

    it('should have no tooltip and enabled results with no disabling function', async () => {
        const wrapper = await createEntitySingleSelect({
            propsData: {
                value: null,
                entity: 'test'
            },
            provide: {
                repositoryFactory: {
                    create: () => {
                        return {
                            search: () => Promise.resolve(getCollection())
                        };
                    }
                }
            }
        });
        await flushPromises();

        await wrapper.find('input').trigger('click');
        await wrapper.vm.$nextTick();

        const firstEntry = wrapper.find('.sw-select-option--0');
        expect(firstEntry.attributes('tooltip-message')).toBeFalsy();
        expect(firstEntry.attributes('tooltip-disabled')).toBe('true');
        expect(wrapper.find('.sw-select-option--0').classes()).not.toContain('is--disabled');
    });

    it('should show disabled selection tooltip when appropriate', async () => {
        const wrapper = await createEntitySingleSelect({
            propsData: {
                value: null,
                entity: 'test',
                selectionDisablingMethod: item => item.name === 'second entry',
                disabledSelectionTooltip: { message: 'test message' }
            },
            provide: {
                repositoryFactory: {
                    create: () => {
                        return {
                            search: () => Promise.resolve(getCollection())
                        };
                    }
                }
            }
        });
        await flushPromises();

        await wrapper.find('input').trigger('click');
        await wrapper.vm.$nextTick();

        const firstEntry = wrapper.find('.sw-select-option--0');
        expect(firstEntry.attributes('tooltip-message')).toBe('test message');
        expect(firstEntry.attributes('tooltip-disabled')).toBe('true');
        const secondEntry = wrapper.find('.sw-select-option--1');
        expect(secondEntry.attributes('tooltip-message')).toBe('test message');
        expect(secondEntry.attributes('tooltip-disabled')).toBe('false');
    });

    it('should show active state of options if enabled', async () => {
        const wrapper = await createEntitySingleSelect({
            propsData: {
                value: null,
                entity: 'test',
                shouldShowActiveState: false,
            },
            provide: {
                repositoryFactory: {
                    create: () => {
                        return {
                            search: () => Promise.resolve(getCollection())
                        };
                    }
                }
            }
        });
        await flushPromises();

        await wrapper.find('input').trigger('click');
        await wrapper.vm.$nextTick();

        let activeStateIcons = wrapper.findAll('.sw-entity-single-select__selection-active');

        expect(activeStateIcons.length).toBe(0);

        await wrapper.setProps({
            shouldShowActiveState: true
        });
        await wrapper.vm.$nextTick();

        activeStateIcons = wrapper.findAll('.sw-entity-single-select__selection-active');

        const activeIconProps = {
            color: '#37d046',
            name: 'default-basic-shape-circle-filled',
            size: '6'
        };

        const inActiveIconProps = {
            color: '#d1d9e0',
            name: 'default-basic-shape-circle-filled',
            size: '6'
        };

        expect(activeStateIcons.length).toBe(3);
        expect(activeStateIcons.at(0).props()).toStrictEqual(activeIconProps);
        expect(activeStateIcons.at(1).props()).toStrictEqual(inActiveIconProps);
        expect(activeStateIcons.at(2).props()).toStrictEqual(activeIconProps);

        await wrapper.setProps({
            shouldShowActiveState: false
        });
        await wrapper.vm.$nextTick();

        activeStateIcons = wrapper.findAll('.sw-select-option .sw-entity-single-select__selection-active');

        expect(activeStateIcons.length).toBe(0);
    });

    it('should have a reset option when it is defined an the value is null', async () => {
        const swEntitySingleSelect = await createEntitySingleSelect({
            propsData: {
                value: null,
                entity: 'test',
                resetOption: 'reset'
            }
        });
        await flushPromises();

        const { singleSelection } = swEntitySingleSelect.vm;

        expect(singleSelection).not.toBeNull();
        expect(singleSelection.id).toBeNull();
        expect(singleSelection.name).toEqual('reset');
    });

    it('should have no reset option when it is defined but the value is not null', async () => {
        const swEntitySingleSelect = await createEntitySingleSelect({
            propsData: {
                value: 'uuid',
                entity: 'test',
                resetOption: 'reset'
            }
        });
        await flushPromises();

        await swEntitySingleSelect.vm.$nextTick();

        const { singleSelection } = swEntitySingleSelect.vm;

        expect(singleSelection).not.toBeNull();
        expect(singleSelection.id).toEqual('uuid');
        expect(singleSelection.name).toEqual('uuid');
    });

    it('should have prepend reset option to resultCollection when resetOption is given', async () => {
        const swEntitySingleSelect = await createEntitySingleSelect({
            propsData: {
                value: '',
                entity: 'test',
                resetOption: 'reset'
            },
            provide: {
                repositoryFactory: {
                    create: () => {
                        return {
                            search: () => Promise.resolve(getCollection())
                        };
                    }
                }
            }
        });
        await flushPromises();

        swEntitySingleSelect.vm.loadData();
        await swEntitySingleSelect.vm.$nextTick();

        const { resultCollection } = swEntitySingleSelect.vm;

        expect(resultCollection.length).toEqual(getCollection().length + 1);
        expect(resultCollection[0].name).toEqual('reset');
    });

    it('should not show the selected item on first entry', async () => {
        const secondItemId = `${fixture[2].id}`;

        const wrapper = await createEntitySingleSelect({
            propsData: {
                value: secondItemId,
                entity: 'test',
                resetOption: 'reset'
            },
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
                            }
                        };
                    }
                }
            }
        });
        await flushPromises();

        await wrapper.find('input').trigger('click');
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-select-option--0').text()).toBe('reset');
        expect(wrapper.find('.sw-select-option--1').text()).toBe('first entry');
        expect(wrapper.find('.sw-select-option--2').text()).toBe('second entry');
        expect(wrapper.find('.sw-select-option--3').text()).toBe('third entry');
    });

    it('should not emit the paginate event when user does not scroll to the end of list', async () => {
        const wrapper = await createEntitySingleSelect({
            propsData: {
                value: '',
                entity: 'test',
                resetOption: 'reset'
            },
            provide: {
                repositoryFactory: {
                    create: () => {
                        return {
                            search: () => Promise.resolve(getCollection())
                        };
                    }
                }
            }
        });
        await flushPromises();

        await wrapper.find('input').trigger('click');
        await wrapper.vm.$nextTick();

        const selectResultList = wrapper.find('.sw-select-result-list');
        const listContent = wrapper.find('.sw-select-result-list__content');

        Object.defineProperty(listContent.element, 'scrollHeight', { value: 1050 });
        Object.defineProperty(listContent.element, 'clientHeight', { value: 250 });
        Object.defineProperty(listContent.element, 'scrollTop', { value: 150 });

        await listContent.trigger('scroll');

        expect(selectResultList.emitted('paginate')).toBe(undefined);
    });

    it('should emit the paginate event when user scroll to the end of list', async () => {
        const wrapper = await createEntitySingleSelect({
            propsData: {
                value: '',
                entity: 'test',
                resetOption: 'reset'
            },
            provide: {
                repositoryFactory: {
                    create: () => {
                        return {
                            search: () => Promise.resolve(getCollection())
                        };
                    }
                }
            }
        });
        await flushPromises();

        await wrapper.find('input').trigger('click');
        await wrapper.vm.$nextTick();

        const selectResultList = wrapper.find('.sw-select-result-list');
        const listContent = wrapper.find('.sw-select-result-list__content');

        Object.defineProperty(listContent.element, 'scrollHeight', { value: 1050 });
        Object.defineProperty(listContent.element, 'clientHeight', { value: 250 });
        Object.defineProperty(listContent.element, 'scrollTop', { value: 800 });

        await listContent.trigger('scroll');

        expect(selectResultList.emitted('paginate')).not.toBe(undefined);
        expect(selectResultList.emitted('paginate').length).toEqual(1);
        expect(selectResultList.emitted('paginate')[0]).toEqual([]);
    });

    it('should emit the correct search term', async () => {
        const swEntitySingleSelect = await createEntitySingleSelect({
            propsData: {
                value: null,
                entity: 'property_group_option'
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
            propsData: {
                value: fixture[0].id,
                entity: 'test',
                displayVariants: true,
                resetOption: 'reset'
            },
            provide: {
                repositoryFactory: {
                    create: () => {
                        return {
                            get: () => Promise.resolve(fixture[0])
                        };
                    }
                }
            }
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
            propsData: {
                value: fixture[0].id,
                entity: 'test',
                labelCallback: () => 'test'
            },
            provide: {
                repositoryFactory: {
                    create: () => {
                        return {
                            get: () => Promise.resolve(fixture[0]),
                            search: () => Promise.resolve(getCollection())
                        };
                    }
                }
            }
        });
        await flushPromises();

        await swEntitySingleSelect.vm.$nextTick();
        expect(swEntitySingleSelect.find('.sw-entity-single-select__selection-text').text())
            .toEqual('test');

        await swEntitySingleSelect.find('input').trigger('click');
        await swEntitySingleSelect.vm.$nextTick();

        expect(swEntitySingleSelect.find('input').element.value).toBe('test');
        expect(swEntitySingleSelect.find('.sw-select-result__result-item-text').text()).toBe('test');
    });

    it('should show the clearable icon in the single select', async () => {
        const wrapper = await createEntitySingleSelect({
            attrs: {
                showClearableButton: true
            }
        });

        const clearableIcon = wrapper.find('.sw-select__select-indicator-clear');
        expect(clearableIcon.isVisible()).toBe(true);
    });

    it('should clear the selection when clicking on clear icon', async () => {
        const wrapper = await createEntitySingleSelect({
            propsData: {
                value: fixture[0].id,
                entity: 'test',
                labelCallback: () => 'test'
            },
            provide: {
                repositoryFactory: {
                    create: () => {
                        return {
                            get: () => Promise.resolve(fixture[0]),
                            search: () => Promise.resolve(getCollection())
                        };
                    }
                }
            },
            attrs: {
                showClearableButton: true
            }
        });
        await flushPromises();

        // wait until fetched data gets rendered
        await wrapper.vm.$nextTick();

        // expect test value selected
        let selectionText = wrapper.find('.sw-entity-single-select__selection-text');
        expect(selectionText.text())
            .toEqual('test');

        // expect no emitted value
        expect(wrapper.emitted('change')).toEqual(undefined);

        // click on clear
        const clearableIcon = wrapper.find('.sw-select__select-indicator-clear');
        await clearableIcon.trigger('click');

        // expect emitting resetting value
        const emittedChangeValue = wrapper.emitted('change')[0];
        expect(emittedChangeValue).toEqual([null]);

        // emulate v-model change
        await wrapper.setProps({
            value: emittedChangeValue[0]
        });

        // expect empty selection
        selectionText = wrapper.find('.sw-entity-single-select__selection-text');
        expect(selectionText.text()).toEqual('');
    });

    it('should show description line in results list', async () => {
        const swEntitySingleSelect = await createEntitySingleSelect({
            scopedSlots: {
                'result-label-property': `<template>
                        {{ props.item.name }}
                    </template>`,
                'result-description-property': `<template>
                        {{ props.item.group.name }}
                    </template>`
            },
            propsData: {
                value: null,
                entity: 'property_group_option'
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
        await flushPromises();

        swEntitySingleSelect.vm.loadData();
        await flushPromises();

        await swEntitySingleSelect.find('.sw-select__selection').trigger('click');
        await swEntitySingleSelect.find('input').trigger('change');
        await flushPromises();

        const firstListEntry = swEntitySingleSelect.findAll('.sw-select-result-list__item-list li').at(0);

        expect(firstListEntry.find('.sw-select-result').classes()).toContain('has--description');
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
            0
        );

        const existingEntityMock = new EntityCollection(
            '',
            '',
            Shopware.Context.api,
            null,
            [
                {
                    id: '12345asd'
                }
            ],
            1
        );

        const swOriginEntitySingleSelect = await Shopware.Component.build('sw-entity-single-select');
        const createableWrapper = shallowMount(swOriginEntitySingleSelect, {
            stubs: {
                'sw-select-base': await Shopware.Component.build('sw-select-base'),
                'sw-block-field': await Shopware.Component.build('sw-block-field'),
                'sw-base-field': await Shopware.Component.build('sw-base-field'),
                'sw-select-result-list': await Shopware.Component.build('sw-select-result-list'),
                'sw-select-result': true,
                'sw-highlight-text': await Shopware.Component.build('sw-highlight-text'),
                'sw-popover': true,
                'sw-field-error': true,
                'sw-loader': true,
                'sw-icon': true,
            },
            propsData: {
                value: 'asdf555',
                entity: 'product_manufacturer',
                allowEntityCreation: true
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
                                0
                            ));
                        },
                        get: () => Promise.resolve({
                            id: 'manufacturerId',
                            name: 'ThisIsMyEntity',
                            product: []
                        }),
                        create: () => Promise.resolve({})
                    }),
                }
            },
            computed: {
                searchCriteria() {
                    return {};
                }
            }
        });
        await flushPromises();

        const displaySearchSpy = jest.spyOn(createableWrapper.vm, 'displaySearch');
        const input = createableWrapper.find('.sw-entity-single-select__selection-input');

        await createableWrapper.find('.sw-select__selection').trigger('click');

        // Enter a new search term
        await input.setValue('Cars');

        // Flush debouncedSearch from parent "sw-entity-single-select" component
        const select = swOriginEntitySingleSelect.methods.debouncedSearch;
        await select.flush();

        // Wait for rendering
        await createableWrapper.vm.$nextTick();
        // Ensure manufacturer does not exist
        expect(createableWrapper.vm.entityExists).toBe(false);

        // Ensure non-existing manufacturer is offered to be created by a new select result item
        expect(createableWrapper.vm.newEntityName).toBe('Cars');
        expect(displaySearchSpy).toHaveBeenCalled();

        await createableWrapper.vm.$nextTick();
        const resultItem = createableWrapper.find('.sw-select-result-list__item-list').find('.sw-highlight-text');

        expect(resultItem.text()).toBe('global.sw-single-select.labelEntityAdd');
        expect(resultItem.props().searchTerm).toBe('Cars');
    });

    it('should reset selected item if it is invalid value', async () => {
        const swEntitySingleSelect = await createEntitySingleSelect({
            propsData: {
                value: fixture[0].id,
                entity: 'test',
            },
            provide: {
                repositoryFactory: {
                    create: () => {
                        return {
                            get: () => Promise.resolve()
                        };
                    }
                }
            }
        });
        await flushPromises();
        expect(swEntitySingleSelect.vm.value).toBe(fixture[0].id);

        await swEntitySingleSelect.setProps({ value: utils.createId() });
        swEntitySingleSelect.vm.$emit('change');

        expect(swEntitySingleSelect.emitted('change')).toBeTruthy();
    });
});
