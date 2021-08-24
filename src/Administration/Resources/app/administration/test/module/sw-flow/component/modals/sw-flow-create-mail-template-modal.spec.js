import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-flow/component/modals/sw-flow-create-mail-template-modal';
import 'src/app/component/form/select/entity/sw-entity-single-select';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/select/base/sw-select-result-list';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/field-base/sw-contextual-field';

const generalTabFieldsClasses = [
    '.sw-flow-create-mail-template-modal__type',
    '.sw-flow-create-mail-template-modal__subject',
    '.sw-flow-create-mail-template-modal__sender-name',
    '.sw-flow-create-mail-template-modal__description'
];

const mailTextTabFieldsClasses = [
    '.sw-flow-create-mail-template-modal__content-plain',
    '.sw-flow-create-mail-template-modal__content-html'
];

const generalTabClass = '.sw-flow-create-mail-template-modal__tab-general';
const mailTextTabClass = '.sw-flow-create-mail-template-modal__tab-mail-text';

const buttonSaveClass = '.sw-flow-create-mail-template-modal__save-button';

let mailTemplate = {
    mailTemplateTypeId: 'abc'
};

function createWrapper(privileges = []) {
    return shallowMount(Shopware.Component.build('sw-flow-create-mail-template-modal'), {
        provide: { repositoryFactory: {
            create: () => {
                return {
                    create: () => {
                        return Promise.resolve();
                    },
                    search: () => Promise.resolve([]),
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
        mailService: {},
        validationService: {},
        entityMappingService: {},
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
            'sw-tabs-item': true,
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
            'sw-tabs': {
                template: '<div class="sw-tabs"><slot></slot><slot name="content" active="content"></slot></div>'
            },
            'sw-code-editor': true,
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

        generalTabFieldsClasses.forEach(elementClass => {
            expect(wrapper.find(elementClass).exists()).toBe(true);
        });

        await wrapper.find(mailTextTabClass).trigger('click');

        mailTextTabFieldsClasses.forEach(elementClass => {
            expect(wrapper.find(elementClass).exists()).toBe(true);
        });
    });

    it('should show error validation message', async () => {
        mailTemplate = {
            mailTemplateTypeId: ''
        };

        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.find(generalTabClass).trigger('click');
        await wrapper.vm.$nextTick();

        await wrapper.find(buttonSaveClass).trigger('click');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.createNotificationError).toBeCalled();
    });
});
