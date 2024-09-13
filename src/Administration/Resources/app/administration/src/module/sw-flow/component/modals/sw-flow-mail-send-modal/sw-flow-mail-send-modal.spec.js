import { mount } from '@vue/test-utils';
import flowState from 'src/module/sw-flow/state/flow.state';

/**
 * @package services-settings
 */

const recipientEmailInputClass = '.sw-flow-mail-send-modal__recipient-email #sw-field--item-email';
const recipientNameInputClass = '.sw-flow-mail-send-modal__recipient-name #sw-field--item-name';

const sequenceFixture = {
    id: '1',
    actionName: 'action.mail.send',
    ruleId: null,
    parentId: null,
    position: 1,
    displayGroup: 1,
    trueCase: false,
    config: {
        mailTemplateId: 'mailTemplate1',
        documentTypeIds: [],
        recipient: {
            type: 'custom',
            data: {
                'test@example.com': 'John Doe',
                'test1@example.com': 'Jane Doe',
            },
        },
    },
};

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
                    name: 'Double opt-in on guest orders',
                },
            },
            translated: {
                description: 'Shopware default template',
            },
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
                    name: 'Customer registration',
                },
            },
            translated: {
                description: 'Registration confirmation',
            },
        },
    ];
}

async function createWrapper(sequence = {}) {
    return mount(await wrapTestComponent('sw-flow-mail-send-modal', { sync: true }), {
        global: {
            stubs: {
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
                    emits: ['click'],
                    template: '<button @click="$emit(\'click\', $event)"><slot></slot></button>',
                },
                'sw-alert': true,
                'sw-entity-multi-id-select': true,
                'sw-entity-single-select': await wrapTestComponent('sw-entity-single-select'),
                'sw-single-select': await wrapTestComponent('sw-single-select'),
                'sw-select-base': await wrapTestComponent('sw-select-base'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
                'sw-data-grid': await wrapTestComponent('sw-data-grid', { sync: true }),
                'sw-text-field': await wrapTestComponent('sw-text-field'),
                'sw-text-field-deprecated': await wrapTestComponent('sw-text-field-deprecated', { sync: true }),
                'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                'sw-help-text': true,
                'sw-icon': true,
                'sw-field-error': {
                    props: ['error'],
                    template: '<div class="sw-field__error"></div>',
                },
                'sw-highlight-text': true,
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
                'sw-popover': {
                    template: '<div class="sw-popover"><slot></slot></div>',
                },
                'sw-context-menu-item': {
                    emits: ['click'],
                    template: '<div @click="$emit(\'click\')"></div>',
                },
                'sw-context-button': {
                    template: '<div class="sw-context-button"><slot></slot></div>',
                },
                'sw-loader': true,
                'router-link': true,
                'sw-flow-create-mail-template-modal': true,
                'sw-product-variant-info': true,
                'sw-inheritance-switch': true,
                'sw-ai-copilot-badge': true,
                'sw-checkbox-field': true,
                'sw-data-grid-settings': true,
                'sw-data-grid-column-boolean': true,
                'sw-data-grid-inline-edit': true,
                'sw-data-grid-skeleton': true,
                'sw-field-copyable': true,
            },
            provide: {
                repositoryFactory: {
                    create: () => {
                        return {
                            create: () => Promise.resolve(),
                            search: () => Promise.resolve(mockMailTemplateData()),
                            get: () => Promise.resolve(),
                        };
                    },
                },
                validationService: {},
            },
        },
        props: {
            sequence,
        },
    });
}

describe('module/sw-flow/component/sw-flow-mail-send-modal', () => {
    beforeAll(() => {
        Shopware.State.registerModule('swFlowState', {
            ...flowState,
        });
    });

    it('should show and remove error on email template field if value is valid', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const mailTemplate = wrapper.find('.sw-flow-mail-send-modal__mail-template-select');

        const buttonSave = wrapper.find('.sw-flow-mail-send-modal__save-button');
        await buttonSave.trigger('click');
        expect(mailTemplate.classes()).toContain('has--error');

        const mailTemplateSelect = wrapper.find('.sw-flow-mail-send-modal__mail-template-select .sw-select__selection');
        await mailTemplateSelect.trigger('click');
        await flushPromises();

        const mailOption = wrapper.findAll('.sw-select-result');
        await mailOption.at(1).trigger('click');

        expect(mailTemplate.classes()).not.toContain('has--error');
    });

    it('should show recipient emails grid if the recipient is custom', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const recipientSelect = wrapper.find('.sw-flow-mail-send-modal__recipient .sw-select__selection');
        await recipientSelect.trigger('click');
        await flushPromises();

        const customOption = wrapper.find('.sw-select-option--custom');
        await customOption.trigger('click');
        await flushPromises();

        const recipientGrid = wrapper.find('.sw-flow-mail-send-modal__recipient-grid');
        expect(recipientGrid.exists()).toBeTruthy();
    });

    it('should show error on fields on recipient emails grid', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const btnEditInline = '.sw-data-grid__cell--actions .sw-data-grid__inline-edit-save';

        const recipientSelect = wrapper.find('.sw-flow-mail-send-modal__recipient .sw-select__selection');
        await recipientSelect.trigger('click');
        await flushPromises();

        const customOption = wrapper.find('.sw-select-option--custom');
        await customOption.trigger('click');
        await flushPromises();

        const saveButton = wrapper.find(btnEditInline);
        await saveButton.trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-flow-mail-send-modal__recipient-email').classes()).toContain('has--error');
        expect(wrapper.find('.sw-flow-mail-send-modal__recipient-name').classes()).toContain('has--error');
    });

    it('should show and remove email valid message on recipient email field', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const recipientSelect = wrapper.find('.sw-flow-mail-send-modal__recipient .sw-select__selection');
        await recipientSelect.trigger('click');
        await flushPromises();

        const customOption = wrapper.find('.sw-select-option--custom');
        await customOption.trigger('click');
        await flushPromises();

        await wrapper.find(recipientEmailInputClass).setValue('invalid');
        await wrapper.find(recipientEmailInputClass).trigger('input');

        await wrapper.find('.sw-data-grid__inline-edit-save').trigger('click');
        await flushPromises();

        expect(wrapper.vm.$data.recipients[0].errorMail._code).toBe('INVALID_MAIL');

        await wrapper.find(recipientEmailInputClass).setValue('test@gmail.com');
        await wrapper.find(recipientEmailInputClass).trigger('input');

        await wrapper.find('.sw-data-grid__inline-edit-save').trigger('click');
        await flushPromises();

        expect(wrapper.vm.$data.recipients[0].errorMail).toBeNull();
    });

    it('should show create mail template modal', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        let createMailTemplateModal = wrapper.find('sw-flow-create-mail-template-modal-stub');
        expect(createMailTemplateModal.exists()).toBeFalsy();

        const mailTemplateSelect = wrapper.find('.sw-flow-mail-send-modal__mail-template-select .sw-select__selection');
        await mailTemplateSelect.trigger('click');
        await flushPromises();

        const createMailTemplate = wrapper.find('.sw-select-result__create-new-template');
        await createMailTemplate.trigger('click');
        await flushPromises();

        createMailTemplateModal = wrapper.find('sw-flow-create-mail-template-modal-stub');
        expect(createMailTemplateModal.exists()).toBeTruthy();
    });

    it('should add an empty row after adding a custom email', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const recipientSelect = wrapper.find('.sw-flow-mail-send-modal__recipient .sw-select__selection');
        await recipientSelect.trigger('click');
        await flushPromises();

        const customOption = wrapper.find('.sw-select-option--custom');
        await customOption.trigger('click');
        await flushPromises();

        let recipientRows = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');
        expect(recipientRows).toHaveLength(1);

        await wrapper.find(recipientEmailInputClass).setValue('test@example.com');
        await wrapper.find(recipientEmailInputClass).trigger('input');

        await wrapper.find(recipientNameInputClass).setValue('John Doe');
        await wrapper.find(recipientNameInputClass).trigger('input');

        await wrapper.find('.sw-data-grid__inline-edit-save').trigger('click');
        await flushPromises();

        recipientRows = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');
        expect(recipientRows).toHaveLength(2);
    });

    it('should show error in recipient grid when clicking on save action button', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const recipientFieldsClasses = [
            '.sw-flow-mail-send-modal__recipient-grid',
            '.sw-flow-mail-send-modal__recipient-email',
            '.sw-flow-mail-send-modal__recipient-name',
        ];

        const recipientSelect = wrapper.find('.sw-flow-mail-send-modal__recipient .sw-select__selection');
        await recipientSelect.trigger('click');
        await flushPromises();

        const customOption = wrapper.find('.sw-select-option--custom');
        await customOption.trigger('click');
        await flushPromises();

        const recipientRows = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');
        expect(recipientRows).toHaveLength(1);

        await wrapper.find('.sw-flow-mail-send-modal__save-button').trigger('click');
        await flushPromises();

        recipientFieldsClasses.forEach(elementClass => {
            expect(wrapper.find(elementClass).classes()).toContain('has--error');
        });
    });

    it('should render correct recipient grid by sequence config', async () => {
        const wrapper = await createWrapper(sequenceFixture);
        await flushPromises();

        const recipientRows = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');
        expect(recipientRows).toHaveLength(3);

        const row1 = wrapper.find('.sw-data-grid__row--0');
        expect(row1.find('.sw-data-grid__cell--email').text()).toContain('test@example.com');
        expect(row1.find('.sw-data-grid__cell--name').text()).toContain('John Doe');

        const row2 = wrapper.find('.sw-data-grid__row--1');
        expect(row2.find('.sw-data-grid__cell--email').text()).toContain('test1@example.com');
        expect(row2.find('.sw-data-grid__cell--name').text()).toContain('Jane Doe');

        const row3 = wrapper.find('.sw-data-grid__row--2');
        expect(row3.find('.sw-data-grid__cell--email').text()).toContain('');
        expect(row3.find('.sw-data-grid__cell--name').text()).toContain('');
    });

    it('should able to remove custom recipient', async () => {
        const wrapper = await createWrapper(sequenceFixture);
        await flushPromises();

        let recipientRows = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');
        expect(recipientRows).toHaveLength(3);

        let row1 = wrapper.find('.sw-data-grid__row--0');
        expect(row1.find('.sw-data-grid__cell--email').text()).toContain('test@example.com');
        expect(row1.find('.sw-data-grid__cell--name').text()).toContain('John Doe');

        await row1.find('.sw-flow-mail-send-modal__grid-action-delete')
            .trigger('click');
        await flushPromises();

        recipientRows = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');
        expect(recipientRows).toHaveLength(2);

        row1 = wrapper.find('.sw-data-grid__row--0');
        expect(row1.find('.sw-data-grid__cell--email').text()).toContain('test1@example.com');
        expect(row1.find('.sw-data-grid__cell--name').text()).toContain('Jane Doe');
    });

    it('should show customer recipient when entity available', async () => {
        Shopware.State.commit('swFlowState/setTriggerEvent', {
            data: {
                customer: '',
                order: '',
            },
            customerAware: true,
            extensions: [],
            mailAware: true,
            name: 'checkout.customer.login',
            aware: [
                'Shopware\\Core\\Framework\\Event\\CustomerAware',
                'Shopware\\Core\\Framework\\Event\\MailAware',
            ],
        });

        const wrapper = await createWrapper();
        await flushPromises();

        const recipientSelect = wrapper.find('.sw-flow-mail-send-modal__recipient .sw-select__selection');
        await recipientSelect.trigger('click');
        await flushPromises();

        const customOption = wrapper.find('.sw-select-option--custom');
        expect(customOption.exists()).toBeTruthy();
        const standardOption = wrapper.find('.sw-select-option--default');
        expect(standardOption.exists()).toBeTruthy();
        const adminOption = wrapper.find('.sw-select-option--admin');
        expect(adminOption.exists()).toBeTruthy();
    });

    it('should show standard recipient for contact form', async () => {
        Shopware.State.commit('swFlowState/setTriggerEvent', {
            data: {
                customer: '',
                order: '',
            },
            customerAware: true,
            extensions: [],
            mailAware: true,
            name: 'contact_form.send',
            aware: [
                'Shopware\\Core\\Framework\\Event\\MailAware',
            ],
        });

        const wrapper = await createWrapper();
        await flushPromises();

        const recipientSelect = wrapper.find('.sw-flow-mail-send-modal__recipient .sw-select__selection');
        await recipientSelect.trigger('click');
        await flushPromises();

        const customOption = wrapper.find('.sw-select-option--custom');
        expect(customOption.exists()).toBeTruthy();
        const contactFormMailOption = wrapper.find('.sw-select-option--contactFormMail');
        expect(contactFormMailOption.exists()).toBeTruthy();
        const standardOption = wrapper.find('.sw-select-option--default');
        expect(standardOption.exists()).toBeTruthy();
        const adminOption = wrapper.find('.sw-select-option--admin');
        expect(adminOption.exists()).toBeTruthy();
    });

    it('should not show standard recipient when entity not available', async () => {
        Shopware.State.commit('swFlowState/setTriggerEvent', {
            data: {
                customer: '',
                order: '',
            },
            customerAware: true,
            extensions: [],
            mailAware: true,
            name: 'checkout.customer.login',
            aware: [
                'Shopware\\Core\\Framework\\Event\\MailAware',
            ],
        });

        const wrapper = await createWrapper();
        await flushPromises();

        const recipientSelect = wrapper.find('.sw-flow-mail-send-modal__recipient .sw-select__selection');
        await recipientSelect.trigger('click');
        await flushPromises();

        const customOption = wrapper.find('.sw-select-option--custom');
        expect(customOption.exists()).toBeTruthy();
        const standardOption = wrapper.find('.sw-select-option--default');
        expect(standardOption.exists()).toBeFalsy();
        const adminOption = wrapper.find('.sw-select-option--admin');
        expect(adminOption.exists()).toBeTruthy();
    });

    it('should show default recipient with newsletter recipient confirm', async () => {
        Shopware.State.commit('swFlowState/setTriggerEvent', {
            data: {},
            customerAware: true,
            extensions: [],
            mailAware: true,
            name: 'newsletter.confirm',
            aware: [
                'Shopware\\Core\\Framework\\Event\\MailAware',
            ],
        });

        const wrapper = await createWrapper();
        await flushPromises();

        const recipientSelect = wrapper.find('.sw-flow-mail-send-modal__recipient .sw-select__selection');
        await recipientSelect.trigger('click');
        await flushPromises();

        const customOption = wrapper.find('.sw-select-option--custom');
        expect(customOption.exists()).toBeTruthy();
        const standardOption = wrapper.find('.sw-select-option--default');
        expect(standardOption.exists()).toBeTruthy();
        const adminOption = wrapper.find('.sw-select-option--admin');
        expect(adminOption.exists()).toBeTruthy();
    });

    it('should show default recipient with newsletter recipient register', async () => {
        Shopware.State.commit('swFlowState/setTriggerEvent', {
            data: {},
            customerAware: true,
            extensions: [],
            mailAware: true,
            name: 'newsletter.register',
            aware: [
                'Shopware\\Core\\Framework\\Event\\MailAware',
            ],
        });

        const wrapper = await createWrapper();
        await flushPromises();

        const recipientSelect = wrapper.find('.sw-flow-mail-send-modal__recipient .sw-select__selection');
        await recipientSelect.trigger('click');
        await flushPromises();

        const customOption = wrapper.find('.sw-select-option--custom');
        expect(customOption.exists()).toBeTruthy();
        const standardOption = wrapper.find('.sw-select-option--default');
        expect(standardOption.exists()).toBeTruthy();
        const adminOption = wrapper.find('.sw-select-option--admin');
        expect(adminOption.exists()).toBeTruthy();
    });

    it('should show default recipient with newsletter recipient unsubscribe', async () => {
        Shopware.State.commit('swFlowState/setTriggerEvent', {
            data: {},
            customerAware: true,
            extensions: [],
            mailAware: true,
            name: 'newsletter.unsubscribe',
            aware: [
                'Shopware\\Core\\Framework\\Event\\MailAware',
            ],
        });

        const wrapper = await createWrapper();
        await flushPromises();

        const recipientSelect = wrapper.find('.sw-flow-mail-send-modal__recipient .sw-select__selection');
        await recipientSelect.trigger('click');
        await flushPromises();

        const customOption = wrapper.find('.sw-select-option--custom');
        expect(customOption.exists()).toBeTruthy();
        const standardOption = wrapper.find('.sw-select-option--default');
        expect(standardOption.exists()).toBeTruthy();
        const adminOption = wrapper.find('.sw-select-option--admin');
        expect(adminOption.exists()).toBeTruthy();
    });

    it('should validate reply to field', async () => {
        const sequence = { ...sequenceFixture, ...{ config: { replyTo: 'test@example.com' } } };
        const wrapper = await createWrapper(sequence);
        await flushPromises();

        wrapper.vm.onAddAction();

        expect(wrapper.vm.showReplyToField).toBeTruthy();
        expect(wrapper.vm.replyToError).toBeNull();
        expect(wrapper.vm.replyToOptions).not.toContain(wrapper.vm.recipientContactFormMail[0]);

        wrapper.vm.changeShowReplyToField('foobar');
        await flushPromises();
        wrapper.vm.onAddAction();

        expect(wrapper.vm.replyToError._code).toBe('INVALID_MAIL');

        wrapper.vm.changeShowReplyToField('default');
        await flushPromises();

        expect(wrapper.vm.showReplyToField).toBeFalsy();
        expect(wrapper.vm.replyTo).toBeNull();
        expect(wrapper.vm.replyToError).toBeNull();

        wrapper.vm.onAddAction();

        expect(wrapper.vm.replyToError).toBeNull();
    });

    it('should validate reply to field with contact form trigger', async () => {
        Shopware.State.commit('swFlowState/setTriggerEvent', {
            name: 'contact_form.send',
        });

        const wrapper = await createWrapper();
        await wrapper.setData({
            triggerEvent: { name: 'contact_form.send' },
        });
        await flushPromises();

        wrapper.vm.onAddAction();

        expect(wrapper.vm.showReplyToField).toBeFalsy();
        expect(wrapper.vm.replyToError).toBeNull();
        expect(wrapper.vm.replyToOptions).toContain(wrapper.vm.recipientContactFormMail[0]);

        wrapper.vm.changeShowReplyToField('foobar');
        await flushPromises();

        wrapper.vm.onAddAction();

        expect(wrapper.vm.showReplyToField).toBeTruthy();
        expect(wrapper.vm.replyToError._code).toBe('INVALID_MAIL');

        wrapper.vm.changeShowReplyToField('contactFormMail');
        await flushPromises();

        expect(wrapper.vm.showReplyToField).toBeFalsy();
        expect(wrapper.vm.replyTo).toBe('contactFormMail');
        expect(wrapper.vm.replyToError).toBeNull();

        wrapper.vm.onAddAction();

        expect(wrapper.vm.replyToError).toBeNull();
    });

    it('should build help text for use different reply-to address switch', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.$tc = jest.fn();
        wrapper.vm.$router = {
            resolve: jest.fn(() => {
                return { href: 'bar' };
            }),
        };
        wrapper.vm.buildReplyToTooltip('foo');

        expect(wrapper.vm.$tc).toHaveBeenCalledWith('foo', 0, { settingsLink: 'bar' });
    });

    it('should be able to close modal', async () => {
        const wrapper = await createWrapper();

        const cancelButton = wrapper.find('.sw-flow-mail-send-modal__cancel-button');
        expect(cancelButton.isVisible()).toBeTruthy();

        await cancelButton.trigger('click');
        await flushPromises();
        expect(wrapper.emitted()['modal-close']).toBeTruthy();
    });

    it('should be able to toggle create mail template modal', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.onCreateMailTemplate();
        expect(wrapper.vm.showCreateMailTemplateModal).toBeTruthy();
        wrapper.vm.onCloseCreateMailTemplateModal();
        expect(wrapper.vm.showCreateMailTemplateModal).toBeFalsy();
    });
});
