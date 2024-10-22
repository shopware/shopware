/**
 * @package buyers-experience
 */

import { mount } from '@vue/test-utils';

const mailHeaderFooterMock = {
    id: '123',
    description: 'Shopware Default Template',
    name: 'Order Header',
    salesChannels: [
        {
            name: 'Storefront',
        },
    ],
    isNew: () => false,
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
                isNew: () => true,
            };
        },
    };
};

const createWrapper = async (privileges = []) => {
    return mount(await wrapTestComponent('sw-mail-header-footer-detail', { sync: true }), {
        global: {
            provide: {
                repositoryFactory: {
                    create: () => repositoryMockFactory(),
                },
                mailService: {},
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
            mocks: {
                $route: { params: { id: Shopware.Utils.createId() } },
            },
            stubs: {
                'sw-page': {
                    template: `
                    <div class="sw-page">
                        <slot name="smart-bar-actions"></slot>
                        <slot name="content"></slot>
                        <slot></slot>
                    </div>`,
                },
                'sw-card-view': {
                    template: '<div><slot></slot></div>',
                },
                'sw-card': {
                    template: '<div><slot></slot></div>',
                },
                'sw-button-process': true,
                'sw-language-info': true,
                'sw-entity-multi-select': true,
                'sw-textarea-field': true,
                'sw-text-field': true,
                'sw-code-editor': true,
                'sw-button': true,
                'sw-skeleton': true,
                'sw-language-switch': true,
            },
        },
    });
};

describe('modules/sw-mail-template/page/sw-mail-header-footer-detail', () => {
    let wrapper;

    it('all fields should be disabled without edit permission', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        [
            wrapper.find('.sw-mail-header-footer-detail__save-action'),
            { wrappers: wrapper.findAll('sw-text-field-stub') },
            { wrappers: wrapper.findAll('sw-textarea-field-stub') },
            { wrappers: wrapper.findAll('sw-code-editor-stub') },
            wrapper.find('sw-entity-multi-select-stub'),
        ].forEach((element) => {
            if (!Array.isArray(element.wrappers)) {
                element = { wrappers: [element] };
            }

            element.wrappers.forEach((el) => {
                expect(el.attributes().disabled).toBeTruthy();
            });
        });

        expect(wrapper.vm.tooltipSave).toStrictEqual({
            message: 'sw-privileges.tooltip.warning',
            disabled: false,
            showOnDisabledElements: true,
        });
    });

    it('all fields should be enabled with edit permission', async () => {
        wrapper = await createWrapper(['mail_templates.editor']);
        await flushPromises();

        [
            wrapper.find('.sw-mail-header-footer-detail__save-action'),
            { wrappers: wrapper.findAll('sw-text-field-stub') },
            { wrappers: wrapper.findAll('sw-textarea-field-stub') },
            { wrappers: wrapper.findAll('sw-code-editor-stub') },
            wrapper.find('sw-entity-multi-select-stub'),
        ].forEach((element) => {
            if (!Array.isArray(element.wrappers)) {
                element = { wrappers: [element] };
            }

            element.wrappers.forEach((el) => {
                expect(el.attributes().disabled).toBeFalsy();
            });
        });

        expect(wrapper.vm.tooltipSave).toStrictEqual({
            message: 'CTRL + S',
            appearance: 'light',
        });
    });
});
