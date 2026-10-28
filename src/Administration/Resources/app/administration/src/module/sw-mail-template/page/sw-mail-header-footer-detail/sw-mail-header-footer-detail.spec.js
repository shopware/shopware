/**
 * @sw-package after-sales
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

const createWrapper = async (privileges = [], repository = null) => {
    return mount(await wrapTestComponent('sw-mail-header-footer-detail', { sync: true }), {
        global: {
            provide: {
                repositoryFactory: {
                    create: () => repository ?? repositoryMockFactory(),
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
                'mt-card': {
                    template: '<div><slot></slot></div>',
                },
                'sw-button-process': true,
                'sw-language-info': true,
                'sw-entity-multi-select': true,
                'sw-textarea-field': true,
                'sw-text-field': true,
                'sw-code-editor': true,
                'sw-skeleton': true,
                'sw-language-switch': true,
                'sw-modal': {
                    template: '<div class="sw-modal"><slot></slot><slot name="modal-footer"></slot></div>',
                },
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

    it('should find every selected sales channel that already uses another header and footer, also beyond 25', async () => {
        const salesChannels = Array.from({ length: 30 }, (_, index) => ({
            id: `sales-channel-${index}`,
            name: `Sales channel ${index}`,
            translated: { name: `Sales channel ${index}` },
            mailHeaderFooterId: 'another-header-footer',
        }));
        const repository = {
            ...repositoryMockFactory(),
            get: () => Promise.resolve({ ...mailHeaderFooterMock, salesChannels }),
            // like the API, only return as many sales channels as the criteria asks for
            search: jest.fn((criteria) => Promise.resolve(salesChannels.slice(0, criteria.limit ?? undefined))),
        };

        wrapper = await createWrapper(['mail_templates.editor'], repository);
        await flushPromises();

        await wrapper.find('.sw-mail-header-footer-detail__save-action').trigger('click');
        await flushPromises();

        expect(repository.search).toHaveBeenCalledTimes(1);
        expect(
            wrapper.findAll('.sw-mail-header-footer-detail__sales-channel-list-entry').map((entry) => entry.text()),
        ).toEqual(salesChannels.map(({ name }) => name));
    });
});
