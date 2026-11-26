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
            id: 'sales-channel-id',
            name: 'Storefront',
        },
    ],
    isNew: () => false,
};

const searchMock = jest.fn();
const saveMock = jest.fn(() => Promise.resolve());

const saveButtonStub = {
    template: '<button @click="$emit(\'click\', $event)"><slot></slot></button>',
    emits: ['click'],
};

const repositoryMockFactory = () => {
    return {
        search: searchMock,
        save: saveMock,
        get: () => {
            return Promise.resolve({
                ...mailHeaderFooterMock,
                salesChannels: mailHeaderFooterMock.salesChannels.map((salesChannel) => ({ ...salesChannel })),
            });
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

const createWrapper = async (privileges = [], stubs = {}) => {
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
                ...stubs,
            },
        },
    });
};

describe('modules/sw-mail-template/page/sw-mail-header-footer-detail', () => {
    let wrapper;

    beforeEach(() => {
        searchMock.mockReset().mockResolvedValue([]);
        saveMock.mockClear();
    });

    const saveAndCaptureSearchCriteria = async () => {
        wrapper = await createWrapper(['mail_templates.editor'], {
            'sw-button-process': saveButtonStub,
        });
        await flushPromises();

        await wrapper.find('.sw-mail-header-footer-detail__save-action').trigger('click');
        await flushPromises();

        expect(searchMock).toHaveBeenCalled();

        return searchMock.mock.calls.at(-1)[0];
    };

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

    it('should not limit the sales channels when looking for already assigned ones', async () => {
        const criteria = await saveAndCaptureSearchCriteria();

        expect(criteria.limit).toBeNull();
    });

    it('should let the server pick the sales channels assigned to another mail header and footer', async () => {
        const criteria = await saveAndCaptureSearchCriteria();

        expect(criteria.ids).toEqual(['sales-channel-id']);
        expect(criteria.filters).toEqual([
            {
                type: 'not',
                operator: 'OR',
                queries: [
                    { type: 'equals', field: 'mailHeaderFooterId', value: null },
                    { type: 'equals', field: 'mailHeaderFooterId', value: expect.any(String) },
                ],
            },
        ]);
    });

    it('should not warn about sales channels that are no longer assigned', async () => {
        searchMock.mockImplementation((criteria) => {
            if (!criteria.ids?.includes('sales-channel-id')) {
                return Promise.resolve([]);
            }

            return Promise.resolve([
                {
                    id: 'sales-channel-id',
                    translated: { name: 'Storefront' },
                },
            ]);
        });

        wrapper = await createWrapper(['mail_templates.editor'], {
            'sw-button-process': saveButtonStub,
            'sw-modal': {
                template: `
                    <div class="sw-modal">
                        <button class="sw-modal__close" @click="$emit('modal-close')"></button>
                        <slot></slot>
                    </div>`,
            },
        });
        await flushPromises();

        await wrapper.find('.sw-mail-header-footer-detail__save-action').trigger('click');
        await flushPromises();

        expect(wrapper.findAll('.sw-mail-header-footer-detail__sales-channel-list-entry')).toHaveLength(1);

        await wrapper.find('.sw-modal__close').trigger('click');
        await wrapper.findComponent('.sw-mail-header-footer-detail__sales-channel').vm.$emit('update:entityCollection', []);

        await wrapper.find('.sw-mail-header-footer-detail__save-action').trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-modal').exists()).toBe(false);
        expect(saveMock).toHaveBeenCalledTimes(1);
    });
});
