import { mount } from '@vue/test-utils';

import EntityCollection from 'src/core/data/entity-collection.data';
import flowState from 'src/module/sw-flow/state/flow.state';

/**
 * @package services-settings
 * @group disabledCompat
 */

const fieldClasses = [
    '.sw-flow-tag-modal__to-field',
    '.sw-flow-tag-modal__tags-field',
];

function getTagCollection(collection = []) {
    return new EntityCollection(
        '/tag',
        'tag',
        null,
        { isShopwareContext: true },
        collection,
        collection.length,
        null,
    );
}

async function createWrapper() {
    return mount(await wrapTestComponent('sw-flow-tag-modal', {
        sync: true,
    }), {
        props: {
            sequence: {
                config: {},
                id: '123',
            },
        },
        global: {
            provide: {
                flowBuilderService: {
                    getActionModalName: () => {},

                    getAvailableEntities: () => {
                        return [
                            {
                                label: 'Order',
                                value: 'order',
                            },
                            {
                                label: 'Customer',
                                value: 'customer',
                            },
                        ];
                    },
                },
                repositoryFactory: {
                    create: () => {
                        return {
                            search: () => Promise.resolve(),
                        };
                    },
                },
            },
            stubs: {
                'sw-entity-tag-select': await wrapTestComponent('sw-entity-tag-select'),
                'sw-single-select': await wrapTestComponent('sw-single-select'),
                'sw-select-base': await wrapTestComponent('sw-select-base'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
                'sw-select-selection-list': await wrapTestComponent('sw-select-selection-list'),
                'sw-modal': {
                    template: `
                    <div class="sw-modal">
                      <slot name="modal-header"></slot>
                      <slot></slot>
                      <slot name="modal-footer"></slot>
                    </div>
                `,
                },
                'sw-button': {
                    template: '<button @click="$emit(\'click\', $event)"><slot></slot></button>',
                },
                'sw-popover': {
                    template: '<div class="sw-popover"><slot></slot></div>',
                },
                'sw-select-result': {
                    props: ['item', 'index'],
                    template: `
                        <li class="sw-select-result" @click.stop="onClickResult">
                            <slot></slot>
                        </li>`,
                    methods: {
                        onClickResult() {
                            this.$parent.$parent.$emit('item-select', this.item);
                        },
                    },
                },
                'sw-loader': true,
                'sw-label': true,
                'sw-icon': true,
                'sw-field-error': true,
                'sw-highlight-text': true,
                'sw-product-variant-info': true,
                'sw-inheritance-switch': true,
                'sw-ai-copilot-badge': true,
                'sw-help-text': true,
            },
        },
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
                        type: 'entity',
                    },
                    order: {
                        type: 'entity',
                    },
                },
                customerAware: true,
                extensions: [],
                logAware: false,
                mailAware: true,
                name: 'checkout.customer.login',
                orderAware: false,
                salesChannelAware: true,
                userAware: false,
                webhookAware: true,
            },
        },
    });

    it('should show these fields on modal', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        fieldClasses.forEach(elementClass => {
            expect(wrapper.find(elementClass).exists()).toBe(true);
        });
    });

    it('should show error if these fields are invalid', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const removeEntity = wrapper.find('.sw-select__select-indicator-clear');
        await removeEntity.trigger('click');
        await flushPromises();

        const buttonSave = wrapper.find('.sw-flow-tag-modal__save-button');
        await buttonSave.trigger('click');
        await flushPromises();

        fieldClasses.forEach(elementClass => {
            expect(wrapper.find(elementClass).classes()).toContain('has--error');
        });
    });

    it('should remove error if these fields are valid', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const removeEntity = wrapper.find('.sw-select__select-indicator-clear');
        await removeEntity.trigger('click');
        await flushPromises();

        const buttonSave = wrapper.find('.sw-flow-tag-modal__save-button');
        await buttonSave.trigger('click');
        await flushPromises();

        fieldClasses.forEach(elementClass => {
            expect(wrapper.find(elementClass).classes()).toContain('has--error');
        });

        await wrapper.setData({
            tagCollection: getTagCollection([{ name: 'new', id: '124' }]),
        });

        const entitySelect = wrapper.find('.sw-single-select__selection');
        await entitySelect.trigger('click');
        await flushPromises();

        const entityItem = wrapper.findAll('.sw-select-result');
        await entityItem.at(0).trigger('click');
        await flushPromises();

        await buttonSave.trigger('click');
        await flushPromises();

        fieldClasses.forEach(elementClass => {
            expect(wrapper.find(elementClass).classes()).not.toContain('has--error');
        });
    });

    it('should show correctly the entity options', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.entityOptions).toHaveLength(2);
        wrapper.vm.entityOptions.forEach((option) => {
            expect(['Order', 'Customer']).toContain(option.label);
        });
    });

    it('should display the title of tag correctly', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

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
        await flushPromises();

        await wrapper.setProps({
            action: 'action.delete.order.tag',
        });

        const titleElement = wrapper.find('.sw-flow-tag-modal');
        expect(titleElement.attributes().title).toBe('');
    });
});
