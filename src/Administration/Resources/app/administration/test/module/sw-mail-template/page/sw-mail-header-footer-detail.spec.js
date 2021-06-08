import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/module/sw-mail-template/page/sw-mail-header-footer-detail';

const mailHeaderFooterMock = {
    id: '123',
    description: 'Shopware Default Template',
    name: 'Order Header',
    salesChannels: [
        {
            name: 'Storefront'
        }
    ],
    isNew: () => false
};

const repositoryMockFactory = () => {
    return {
        search: () => Promise.resolve({}),
        get: () => {
            return Promise.resolve(mailHeaderFooterMock);
        },
        create: () => {
            return {
                description: '',
                name: '',
                isNew: () => true
            };
        }
    };
};

const createWrapper = (privileges = []) => {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-mail-header-footer-detail'), {
        localVue,
        provide: {
            repositoryFactory: {
                create: () => repositoryMockFactory()
            },
            mailService: {},
            entityMappingService: {
                getEntityMapping: () => []
            },
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                }
            }
        },
        mocks: {
            $route: { params: { id: Shopware.Utils.createId() } }
        },
        stubs: {
            'sw-page': {
                template: `
                    <div class="sw-page">
                        <slot name="smart-bar-actions"></slot>
                        <slot name="content"></slot>
                        <slot></slot>
                    </div>`
            },
            'sw-card-view': {
                template: '<div><slot></slot></div>'
            },
            'sw-card': {
                template: '<div><slot></slot></div>'
            },
            'sw-button-process': true,
            'sw-language-info': true,
            'sw-entity-multi-select': true,
            'sw-field': true,
            'sw-code-editor': true,
            'sw-button': true
        }
    });
};

describe('modules/sw-mail-template/page/sw-mail-header-footer-detail', () => {
    let wrapper;
    beforeEach(() => {
        wrapper = createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('all fields should be disabled without edit permission', async () => {
        wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        [
            wrapper.find('.sw-mail-header-footer-detail__save-action'),
            wrapper.findAll('sw-field-stub'),
            wrapper.findAll('sw-code-editor-stub'),
            wrapper.find('sw-entity-multi-select-stub')
        ].forEach(element => {
            if (element.length > 1) {
                element.wrappers.forEach(el => {
                    expect(el.attributes().disabled).toBeTruthy();
                });
            } else {
                expect(element.attributes().disabled).toBeTruthy();
            }
        });

        expect(wrapper.vm.tooltipSave).toStrictEqual({
            message: 'sw-privileges.tooltip.warning',
            disabled: false,
            showOnDisabledElements: true
        });
    });

    it('all fields should be enabled with edit permission', async () => {
        wrapper = createWrapper(['mail_templates.editor']);
        await wrapper.vm.$nextTick();

        [
            wrapper.find('.sw-mail-header-footer-detail__save-action'),
            wrapper.findAll('sw-field-stub'),
            wrapper.findAll('sw-code-editor-stub'),
            wrapper.find('sw-entity-multi-select-stub')
        ].forEach(element => {
            if (element.length > 1) {
                element.wrappers.forEach(el => {
                    expect(el.attributes().disabled).toBeFalsy();
                });
            } else {
                expect(element.attributes().disabled).toBeFalsy();
            }
        });

        expect(wrapper.vm.tooltipSave).toStrictEqual({
            message: 'CTRL + S',
            appearance: 'light'
        });
    });
});
