import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-flow/component/modals/sw-flow-create-mail-template-modal';
import 'src/app/component/form/select/entity/sw-entity-single-select';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/select/base/sw-select-result-list';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/sw-code-editor';

const fieldsClasses = [
    '.sw-flow-create-mail-template-modal__type',
    '.sw-flow-create-mail-template-modal__subject',
    '.sw-flow-create-mail-template-modal__sender-name',
    '.sw-flow-create-mail-template-modal__description',
    '.sw-flow-create-mail-template-modal__content-plain',
    '.sw-flow-create-mail-template-modal__content-html'
];

const buttonSaveClass = '.sw-flow-create-mail-template-modal__save-button';

let mailTemplate = {
    mailTemplateTypeId: 'abc'
};

const mockMailTemplateData = [
    {
        id: 'c8576912ec4f4cb7881dc8f7f2c7c4c4',
        name: 'Cancellation invoice',
        technicalName: 'cancellation_mail',
        translated: {
            name: 'Double opt-in on guest orders'
        }
    },

    {
        id: 'c8576912ec4f4cb7881dc8f7f2c7c412',
        name: 'Customer registration',
        translated: {
            name: 'Customer registration'
        }
    }
];

function createWrapper(privileges = []) {
    return shallowMount(Shopware.Component.build('sw-flow-create-mail-template-modal'), {
        provide: { repositoryFactory: {
            create: (entity) => {
                if (entity === 'mail_template_type') {
                    return {
                        create: () => {
                            return Promise.resolve();
                        },
                        get: () => Promise.resolve({
                            id: 'c8576912ec4f4cb7881dc8f7f2c7c412',
                            customFields: null,
                            name: 'Cancellation invoice',
                            technicalName: 'cancellation_mail',
                            translated: {
                                name: 'Customer registration'
                            }
                        }),
                        search: () => {
                            return Promise.resolve(mockMailTemplateData);
                        }
                    };
                }

                return {
                    create: () => {
                        return Promise.resolve();
                    },
                    search: () => {
                        return Promise.resolve([]);
                    },
                    get: () => Promise.resolve({
                        id: 'c8576912ec4f4cb7881dc8f7f2c7c412',
                        name: 'Customer registration',
                        translated: {
                            name: 'Customer registration'
                        }
                    }),
                    save: () => {
                        if (mailTemplate.mailTemplateTypeId) {
                            return Promise.resolve();
                        }
                        // eslint-disable-next-line prefer-promise-reject-errors
                        return Promise.reject({
                            response: {
                                data: {
                                    errors: [
                                        {
                                            code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                                            detail: 'This value should not be blank.',
                                            status: '400',
                                            template: 'This value should not be blank.'
                                        }
                                    ]
                                }
                            }
                        });
                    }
                };
            }
        },
        userInputSanitizeService: {},
        mailService: {},
        validationService: {},
        entityMappingService: {
            getEntityMapping: () => []
        },
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
            'sw-entity-single-select': Shopware.Component.build('sw-entity-single-select'),
            'sw-select-base': Shopware.Component.build('sw-select-base'),
            'sw-block-field': Shopware.Component.build('sw-block-field'),
            'sw-base-field': Shopware.Component.build('sw-base-field'),
            'sw-text-field': Shopware.Component.build('sw-text-field'),
            'sw-select-result-list': Shopware.Component.build('sw-select-result-list'),
            'sw-contextual-field': Shopware.Component.build('sw-contextual-field'),
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
            'sw-code-editor': Shopware.Component.build('sw-code-editor'),
            'sw-textarea-field': true,
            'sw-container': true,
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
            'sw-loader': true
        }
    });
}

describe('module/sw-flow/component/sw-flow-create-mail-template-modal', () => {
    it('should show element correctly', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        fieldsClasses.forEach(elementClass => {
            expect(wrapper.find(elementClass).exists()).toBe(true);
        });
    });

    it('should able to create a mail template', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        await wrapper.find(`${fieldsClasses[0]} .sw-entity-single-select__selection`).trigger('click');
        await wrapper.vm.$nextTick();

        const typeElement = wrapper.findAll('.sw-select-result');
        await typeElement.at(0).trigger('click');

        await wrapper.find(`${fieldsClasses[1]} input`).setValue('Subject');
        await wrapper.find(`${fieldsClasses[1]} input`).trigger('input');

        await wrapper.find(`${fieldsClasses[4]} textarea`).setValue('Code');
        await wrapper.find(`${fieldsClasses[4]} textarea`).trigger('input');

        await wrapper.find(`${fieldsClasses[5]} textarea`).setValue('Code');
        await wrapper.find(`${fieldsClasses[5]} textarea`).trigger('input');

        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.find(buttonSaveClass).trigger('click');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.createNotificationError).toBeCalledTimes(0);
    });

    it('should show error validation message', async () => {
        mailTemplate = {
            mailTemplateTypeId: ''
        };

        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.find(buttonSaveClass).trigger('click');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.createNotificationError).toBeCalled();
    });
});
