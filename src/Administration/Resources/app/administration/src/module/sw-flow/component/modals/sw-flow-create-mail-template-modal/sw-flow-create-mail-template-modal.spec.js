import { mount } from '@vue/test-utils';

const fieldsClasses = [
    '.sw-flow-create-mail-template-modal__type',
    '.sw-flow-create-mail-template-modal__subject', // n
    '.sw-flow-create-mail-template-modal__sender-name', // n
    '.sw-flow-create-mail-template-modal__description',
    '.sw-flow-create-mail-template-modal__content-plain',
    '.sw-flow-create-mail-template-modal__content-html',
];

const buttonSaveClass = '.sw-flow-create-mail-template-modal__save-button';

let mailTemplate = {
    mailTemplateTypeId: 'abc',
};

const mockMailTemplateData = [
    {
        id: 'c8576912ec4f4cb7881dc8f7f2c7c4c4',
        name: 'Cancellation invoice',
        technicalName: 'cancellation_mail',
        translated: {
            name: 'Double opt-in on guest orders',
        },
    },

    {
        id: 'c8576912ec4f4cb7881dc8f7f2c7c412',
        name: 'Customer registration',
        translated: {
            name: 'Customer registration',
        },
    },
];

async function createWrapper(privileges = []) {
    return mount(await wrapTestComponent('sw-flow-create-mail-template-modal', {
        sync: true,
    }), {
        props: {
            sequence: {},
        },
        global: {
            stubs: {
                'sw-entity-single-select': await wrapTestComponent('sw-entity-single-select'),
                'sw-select-base': await wrapTestComponent('sw-select-base'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-text-field': await wrapTestComponent('sw-text-field'),
                'sw-text-field-deprecated': await wrapTestComponent('sw-text-field-deprecated', { sync: true }),
                'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
                'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
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
                'sw-code-editor': await wrapTestComponent('sw-code-editor'),
                'sw-textarea-field': await wrapTestComponent('sw-textarea-field'),
                'sw-container': await wrapTestComponent('sw-container'),
                'sw-icon': true,
                'sw-field-error': {
                    props: ['error'],
                    template: '<div class="sw-field__error"></div>',
                },
                'sw-highlight-text': await wrapTestComponent('sw-highlight-text'),
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
                'sw-loader': true,
            },
            provide: {
                repositoryFactory: {
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
                                        name: 'Customer registration',
                                    },
                                }),
                                search: () => {
                                    return Promise.resolve(mockMailTemplateData);
                                },
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
                                    name: 'Customer registration',
                                },
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
                                                    template: 'This value should not be blank.',
                                                },
                                            ],
                                        },
                                    },
                                });
                            },
                        };
                    },
                },
                userInputSanitizeService: {},
                mailService: {},
                validationService: {},
                entityMappingService: {
                    getEntityMapping: () => [],
                },
                acl: {
                    can: (identifier) => {
                        if (!identifier) {
                            return true;
                        }

                        return privileges.includes(identifier);
                    },
                },
            },
        },
    });
}

describe('module/sw-flow/component/sw-flow-create-mail-template-modal', () => {
    beforeAll(() => {
        Shopware.Context.app.config.settings = {
            enableHtmlSanitizer: true,
        };
    });

    it('should show element correctly', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        fieldsClasses.forEach(elementClass => {
            expect(wrapper.find(elementClass).exists()).toBe(true);
        });
    });

    it('should able to create a mail template', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.find(`${fieldsClasses[0]} .sw-entity-single-select__selection`).trigger('click');
        await flushPromises();

        const typeElement = wrapper.findAll('.sw-select-result');
        await typeElement.at(0).trigger('click');
        await flushPromises();

        const subjectInput = await wrapper.find(`${fieldsClasses[1]} input`);
        await subjectInput.setValue('Subject');
        await subjectInput.trigger('input');
        await flushPromises();

        await wrapper.find(`${fieldsClasses[4]} textarea`).setValue('Code');
        await wrapper.find(`${fieldsClasses[4]} textarea`).trigger('input');
        await flushPromises();

        await wrapper.find(`${fieldsClasses[5]} textarea`).setValue('Code');
        await wrapper.find(`${fieldsClasses[5]} textarea`).trigger('input');
        await flushPromises();

        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.find(buttonSaveClass).trigger('click');
        await flushPromises();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledTimes(0);
    });

    it('should show error validation message', async () => {
        mailTemplate = {
            mailTemplateTypeId: '',
        };

        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.find(buttonSaveClass).trigger('click');
        await flushPromises();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalled();
    });
});
