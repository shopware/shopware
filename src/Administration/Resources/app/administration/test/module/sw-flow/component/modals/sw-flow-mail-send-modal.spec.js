import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-flow/component/modals/sw-flow-mail-send-modal';
import 'src/app/component/form/select/base/sw-single-select';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/select/base/sw-select-result-list';
import 'src/app/component/data-grid/sw-data-grid';
import 'src/app/component/form/select/entity/sw-entity-single-select';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/field-base/sw-contextual-field';

import Vuex from 'vuex';
import flowState from 'src/module/sw-flow/state/flow.state';

const fieldClasses = [
    '.sw-flow-mail-send-modal__recipient',
    '.sw-flow-mail-send-modal__mail-template-select',
    '.sw-flow-mail-send-modal__mail-template-detail',
    '.sw-flow-mail-send-modal__document-types',
    '.sw-flow-mail-send-modal__document_warning'
];

const recipientEmailInputClass = '.sw-flow-mail-send-modal__recipient-email #sw-field--item-email';

function mockMailTemplateData() {
    return [
        {
            id: 'mailTemplate1',
            description: 'Shopware default template',
            subject: 'Your order with {{ salesChannel.name }} is being processed.',
            mailTemplateTypeId: '5',
            mailTemplateType: {
                id: '89',
                name: 'Double opt-in on guest orders',
                translated: {
                    name: 'Double opt-in on guest orders'
                }
            },
            translated: {
                description: 'Shopware default template'
            }
        },
        {
            id: 'mailTemplate2',
            description: 'Registration confirmation',
            subject: 'Your order with {{ salesChannel.name }} is being processed.',
            mailTemplateTypeId: '2',
            mailTemplateType: {
                id: '89',
                name: 'Customer registration',
                translated: {
                    name: 'Customer registration'
                }
            },
            translated: {
                description: 'Registration confirmation'
            }
        }
    ];
}

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.use(Vuex);

    return shallowMount(Shopware.Component.build('sw-flow-mail-send-modal'), {
        provide: { repositoryFactory: {
            create: () => {
                return {
                    create: () => Promise.resolve(),
                    search: () => Promise.resolve(mockMailTemplateData())
                };
            }
        },
        validationService: {},
        acl: { can: (identifier) => {
            if (!identifier) {
                return true;
            }

            return privileges.includes(identifier);
        } } },


        propsData: {
            sequence: {}
        },

        stubs: {
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
            'sw-alert': true,
            'sw-entity-multi-id-select': true,
            'sw-entity-single-select': Shopware.Component.build('sw-entity-single-select'),
            'sw-single-select': Shopware.Component.build('sw-single-select'),
            'sw-select-base': Shopware.Component.build('sw-select-base'),
            'sw-block-field': Shopware.Component.build('sw-block-field'),
            'sw-base-field': Shopware.Component.build('sw-base-field'),
            'sw-select-result-list': Shopware.Component.build('sw-select-result-list'),
            'sw-data-grid': Shopware.Component.build('sw-data-grid'),
            'sw-text-field': Shopware.Component.build('sw-text-field'),
            'sw-contextual-field': Shopware.Component.build('sw-contextual-field'),
            'sw-icon': true,
            'sw-field-error': {
                props: ['error'],
                template: '<div class="sw-field__error"></div>'
            },
            'sw-highlight-text': true,
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
            'sw-popover': {
                template: '<div class="sw-popover"><slot></slot></div>'
            },
            'sw-context-menu-item': true,
            'sw-context-button': true,
            'sw-loader': true,
            'router-link': true
        }
    });
}

describe('module/sw-flow/component/sw-flow-mail-send-modal', () => {
    beforeAll(() => {
        Shopware.State.registerModule('swFlowState', {
            ...flowState
        });
    });

    it('should show element correctly', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        fieldClasses.forEach(elementClass => {
            expect(wrapper.find(elementClass).exists()).toBe(true);
        });
    });

    it('should show and remove error on email template field if value is valid', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const mailTemplate = wrapper.find('.sw-flow-mail-send-modal__mail-template-select');

        const buttonSave = wrapper.find('.sw-flow-mail-send-modal__save-button');
        await buttonSave.trigger('click');
        expect(mailTemplate.classes()).toContain('has--error');

        const mailTemplateSelect = wrapper.find('.sw-flow-mail-send-modal__mail-template-select .sw-select__selection');
        await mailTemplateSelect.trigger('click');
        await wrapper.vm.$nextTick();

        const mailOption = wrapper.findAll('.sw-select-result');
        await mailOption.at(1).trigger('click');

        expect(mailTemplate.classes()).not.toContain('has--error');
    });

    it('should show recipient emails grid if the recipient is custom', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();
        const recipientSelect = wrapper.find('.sw-flow-mail-send-modal__recipient .sw-select__selection');
        await recipientSelect.trigger('click');

        const customOption = wrapper.find('.sw-select-option--custom');
        await customOption.trigger('click');
        const recipientGrid = wrapper.find('.sw-flow-mail-send-modal__recipient-grid');

        expect(recipientGrid.exists()).toBe(true);
    });

    it('should show error on fields on recipient emails grid', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const recipientFieldsClasses = [
            '.sw-flow-mail-send-modal__recipient-email',
            '.sw-flow-mail-send-modal__recipient-name'
        ];

        const btnEditInline = '.sw-data-grid__cell--actions .sw-data-grid__inline-edit-save';

        const recipientSelect = wrapper.find('.sw-flow-mail-send-modal__recipient .sw-select__selection');
        await recipientSelect.trigger('click');

        const customOption = wrapper.find('.sw-select-option--custom');
        await customOption.trigger('click');

        const saveButton = wrapper.find(btnEditInline);
        await saveButton.trigger('click');

        recipientFieldsClasses.forEach(elementClass => {
            expect(wrapper.find(elementClass).classes()).toContain('has--error');
        });
    });

    it('should show and remove email valid message on recipient email field', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const recipientSelect = wrapper.find('.sw-flow-mail-send-modal__recipient .sw-select__selection');
        await recipientSelect.trigger('click');

        const customOption = wrapper.find('.sw-select-option--custom');
        await customOption.trigger('click');

        wrapper.find(recipientEmailInputClass).setValue('invalid');
        wrapper.find(recipientEmailInputClass).trigger('input');

        await wrapper.find('.sw-data-grid__inline-edit-save').trigger('click');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.$data.recipients[0].errorMail._code).toBe('INVALID_MAIL');

        wrapper.find(recipientEmailInputClass).setValue('test@gmail.com');
        wrapper.find(recipientEmailInputClass).trigger('input');

        await wrapper.find('.sw-data-grid__inline-edit-save').trigger('click');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.$data.recipients[0].errorMail).toBe(null);
    });
});
