import { shallowMount, createLocalVue } from '@vue/test-utils';
import swFlowTagModal from 'src/module/sw-flow/component/modals/sw-flow-tag-modal';
import 'src/app/component/form/select/base/sw-single-select';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/select/entity/sw-entity-tag-select';
import 'src/app/component/form/select/entity/sw-entity-multi-select';
import 'src/app/component/form/select/base/sw-select-result-list';
import 'src/app/component/form/select/base/sw-select-selection-list';

import EntityCollection from 'src/core/data/entity-collection.data';
import Vuex from 'vuex';
import flowState from 'src/module/sw-flow/state/flow.state';

Shopware.Component.register('sw-flow-tag-modal', swFlowTagModal);

const fieldClasses = [
    '.sw-flow-tag-modal__to-field',
    '.sw-flow-tag-modal__tags-field'
];

function getTagCollection(collection = []) {
    return new EntityCollection(
        '/tag',
        'tag',
        null,
        { isShopwareContext: true },
        collection,
        collection.length,
        null
    );
}

async function createWrapper() {
    const localVue = createLocalVue();
    localVue.use(Vuex);

    return shallowMount(await Shopware.Component.build('sw-flow-tag-modal'), {
        localVue,
        provide: {
            flowBuilderService: {
                getActionModalName: () => {},

                getAvailableEntities: () => {
                    return [
                        {
                            label: 'Order',
                            value: 'order'
                        },
                        {
                            label: 'Customer',
                            value: 'customer'
                        }
                    ];
                }
            },
            repositoryFactory: {
                create: () => {
                    return {
                        search: () => Promise.resolve()
                    };
                }
            }
        },

        propsData: {
            sequence: {}
        },

        stubs: {
            'sw-entity-tag-select': await Shopware.Component.build('sw-entity-tag-select'),
            'sw-single-select': await Shopware.Component.build('sw-single-select'),
            'sw-select-base': await Shopware.Component.build('sw-select-base'),
            'sw-block-field': await Shopware.Component.build('sw-block-field'),
            'sw-base-field': await Shopware.Component.build('sw-base-field'),
            'sw-select-result-list': await Shopware.Component.build('sw-select-result-list'),
            'sw-select-selection-list': await Shopware.Component.build('sw-select-selection-list'),
            'sw-modal': {
                template: `
                    <div class="sw-modal">
                      <slot name="modal-header"></slot>
                      <slot></slot>
                      <slot name="modal-footer"></slot>
                    </div>
                `
            },
            'sw-button': {
                template: '<button @click="$emit(\'click\', $event)"><slot></slot></button>'
            },
            'sw-popover': {
                template: '<div class="sw-popover"><slot></slot></div>'
            },
            'sw-select-result': {
                props: ['item', 'index'],
                template: `<li class="sw-select-result" @click.stop="onClickResult">
                                <slot></slot>
                           </li>`,
                methods: {
                    onClickResult() {
                        this.$parent.$parent.$emit('item-select', this.item);
                    }
                }
            },
            'sw-loader': true,
            'sw-label': true,
            'sw-icon': true,
            'sw-field-error': true,
            'sw-highlight-text': true
        }
    });
}

describe('module/sw-flow/component/sw-flow-tag-modal', () => {
    Shopware.State.registerModule('swFlowState', {
        ...flowState,
        state: {
            invalidSequences: [],
            triggerEvent: {
                data: {
                    customer: {
                        type: 'entity'
                    },
                    order: {
                        type: 'entity'
                    }
                },
                customerAware: true,
                extensions: [],
                logAware: false,
                mailAware: true,
                name: 'checkout.customer.login',
                orderAware: false,
                salesChannelAware: true,
                userAware: false,
                webhookAware: true
            }
        }
    });

    it('should show these fields on modal', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        fieldClasses.forEach(elementClass => {
            expect(wrapper.find(elementClass).exists()).toBe(true);
        });
    });

    it('should show error if these fields are invalid', async () => {
        const wrapper = await createWrapper();
        const removeEntity = wrapper.find('.sw-select__select-indicator-clear');
        await removeEntity.trigger('click');
        const buttonSave = wrapper.find('.sw-flow-tag-modal__save-button');
        await buttonSave.trigger('click');

        fieldClasses.forEach(elementClass => {
            expect(wrapper.find(elementClass).classes()).toContain('has--error');
        });
    });

    it('should remove error if these fields are valid', async () => {
        const wrapper = await createWrapper();

        const removeEntity = wrapper.find('.sw-select__select-indicator-clear');
        await removeEntity.trigger('click');
        const buttonSave = wrapper.find('.sw-flow-tag-modal__save-button');
        await buttonSave.trigger('click');

        fieldClasses.forEach(elementClass => {
            expect(wrapper.find(elementClass).classes()).toContain('has--error');
        });

        await wrapper.setData({
            tagCollection: getTagCollection([{ name: 'new', id: '124' }])
        });

        const entitySelect = wrapper.find('.sw-single-select__selection');
        await entitySelect.trigger('click');

        const entityItem = wrapper.findAll('.sw-select-result');
        await entityItem.at(0).trigger('click');

        await buttonSave.trigger('click');

        fieldClasses.forEach(elementClass => {
            expect(wrapper.find(elementClass).classes()).not.toContain('has--error');
        });
    });

    it('should show correctly the entity options', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.entityOptions).toHaveLength(2);
        wrapper.vm.entityOptions.forEach((option) => {
            expect(['Order', 'Customer']).toContain(option.label);
        });
    });

    it('should display the title of tag correctly', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            action: 'action.add.order.tag',
        });

        const titleElement = wrapper.find('.sw-flow-tag-modal');
        expect(titleElement.attributes().title).toBe('sw-flow.modals.tag.labelAddTag');

        await wrapper.setProps({
            action: 'action.remove.order.tag',
        });
        expect(titleElement.attributes().title).toBe('sw-flow.modals.tag.labelRemoveTag');
    });

    it('should display empty title of tag', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            action: 'action.delete.order.tag',
        });

        const titleElement = wrapper.find('.sw-flow-tag-modal');
        expect(titleElement.attributes().title).toBe('');
    });
});
