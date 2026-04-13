/**
 * @sw-package discovery
 */

import { mount } from '@vue/test-utils';

async function createWrapper() {
    const systemConfigApiService = {
        getValues: jest.fn().mockResolvedValue({
            'core.measurementUnits.system': 'metric',
            'core.measurementUnits.length': 'mm',
            'core.measurementUnits.weight': 'kg',
        }),
    };
    const repositoryFactory = {
        create: () => ({
            create: () => ({}),
            get: () =>
                Promise.resolve({
                    productExports: {
                        first: () => ({}),
                    },
                }),
            search: () => Promise.resolve([]),
        }),
    };

    const wrapper = mount(await wrapTestComponent('sw-sales-channel-create', { sync: true }), {
        global: {
            stubs: {
                'sw-page': {
                    template: `
                        <div class="sw-page">
                            <slot name="smart-bar-actions"></slot>
                        </div>
                    `,
                },
                'sw-button-process': {
                    template: '<button class="sw-button-process"></button>',
                    props: ['disabled'],
                },
                'sw-language-switch': true,
                'sw-card-view': true,
                'sw-language-info': true,
                'sw-tabs': true,
                'sw-tabs-item': true,
                'router-view': true,
                'sw-skeleton': true,
            },
            provide: {
                repositoryFactory,
                exportTemplateService: {
                    getProductExportTemplateRegistry: () => ({}),
                },
                systemConfigApiService,
            },
            mocks: {
                $route: {
                    params: {
                        id: '1a2b3c4d',
                        typeId: 'sales-channel-type-id',
                    },
                    name: '',
                },
            },
        },
    });

    return { wrapper };
}

describe('src/module/sw-sales-channel/page/sw-sales-channel-create', () => {
    beforeEach(() => {
        global.activeAclRoles = [];
    });

    it('should disable the save button when privilege does not exist', async () => {
        const { wrapper } = await createWrapper();
        const saveButton = wrapper.getComponent('.sw-sales-channel-detail__save-action');

        await wrapper.setData({
            isLoading: false,
        });

        expect(saveButton.props('disabled')).toBe(true);
    });

    it('should enable the save button when privilege does exists', async () => {
        global.activeAclRoles = ['sales_channel.creator'];
        const { wrapper } = await createWrapper();

        await wrapper.setData({
            isLoading: false,
        });

        const saveButton = wrapper.getComponent('.sw-sales-channel-detail__save-action');

        expect(saveButton.props('disabled')).toBe(false);
    });

    it('should prepare measurementUnits for salesChannel with values from system config', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.salesChannel.measurementUnits).toEqual({
            system: 'metric',
            units: {
                length: 'mm',
                weight: 'kg',
            },
        });
    });

    it('should set languageId from admin context when creating sales channel', async () => {
        const mockLanguageId = '2fbb5fe2e29a4d70aa5854ce7ce3e20b';

        Shopware.Store.get('context').api.languageId = mockLanguageId;

        const { wrapper } = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.salesChannel.languageId).toBe(mockLanguageId);
    });

    it('should add default language to languages collection when missing', async () => {
        const mockLanguageId = '7c3f39f2c2134f7aa42f4dcf5f3c9b1b';
        const languageCollection = {
            has: jest.fn().mockReturnValue(false),
            add: jest.fn(),
        };
        const languageRepository = {
            get: jest.fn().mockResolvedValue({ id: mockLanguageId }),
        };

        Shopware.Store.get('context').api.languageId = mockLanguageId;

        const { wrapper } = await createWrapper();
        wrapper.vm.salesChannel.languages = languageCollection;
        wrapper.vm.repositoryFactory.create = jest.fn().mockReturnValue(languageRepository);

        wrapper.vm.ensureDefaultLanguageInCollection(mockLanguageId);

        await flushPromises();

        expect(languageRepository.get).toHaveBeenCalledWith(mockLanguageId, expect.any(Object));
        expect(languageCollection.add).toHaveBeenCalledWith(expect.objectContaining({ id: mockLanguageId }));
    });
});
