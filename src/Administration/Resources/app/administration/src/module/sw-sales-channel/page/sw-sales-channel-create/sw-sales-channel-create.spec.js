/**
 * @sw-package discovery
 */

import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-sales-channel-create', { sync: true }), {
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
                repositoryFactory: {
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
                },
                exportTemplateService: {
                    getProductExportTemplateRegistry: () => ({}),
                },
                systemConfigApiService: {
                    getValues: () => {
                        return Promise.resolve({});
                    },
                },
            },
            mocks: {
                $route: {
                    params: {
                        id: '1a2b3c4d',
                    },
                    name: '',
                },
            },
        },
    });
}

describe('src/module/sw-sales-channel/page/sw-sales-channel-create', () => {
    beforeEach(() => {
        global.activeAclRoles = [];
    });

    it('should disable the save button when privilege does not exist', async () => {
        const wrapper = await createWrapper();
        const saveButton = wrapper.getComponent('.sw-sales-channel-detail__save-action');

        await wrapper.setData({
            isLoading: false,
        });

        expect(saveButton.props('disabled')).toBe(true);
    });

    it('should enable the save button when privilege does exists', async () => {
        global.activeAclRoles = ['sales_channel.creator'];
        const wrapper = await createWrapper();

        await wrapper.setData({
            isLoading: false,
        });

        const saveButton = wrapper.getComponent('.sw-sales-channel-detail__save-action');

        expect(saveButton.props('disabled')).toBe(false);
    });

    it('should initialize measurement system values correctly', async () => {
        const wrapper = await createWrapper();

        const mockConfig = {
            'core.measurementSystem.typeId': 'default-system',
            'core.measurementSystem.lengthUnitId': 'default-length',
            'core.measurementSystem.massUnitId': 'default-mass'
        };

        wrapper.vm.systemConfigApiService.getValues = jest.fn().mockResolvedValue(mockConfig);

        wrapper.vm.$route.params.typeId = 'test-type';

        await wrapper.vm.createdComponent();

        expect(wrapper.vm.systemConfigApiService.getValues).toHaveBeenCalledWith('core.measurementSystem');

        expect(wrapper.vm.salesChannel.defaultMeasurementSystemId).toBe('default-system');
        expect(wrapper.vm.salesChannel.defaultLengthUnitId).toBe('default-length');
        expect(wrapper.vm.salesChannel.defaultMassUnitId).toBe('default-mass');
    });

    it('should handle measurement system change correctly', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            measurementSystemConfig: {
                'core.measurementSystem.typeId': 'default-system',
                'core.measurementSystem.lengthUnitId': 'default-length',
                'core.measurementSystem.massUnitId': 'default-mass'
            }
        });

        await wrapper.setData({
            salesChannel: {
                defaultMeasurementSystemId: 'default-system',
                defaultLengthUnitId: 'default-length',
                defaultMassUnitId: 'default-mass'
            }
        });

        await wrapper.setData({
            salesChannel: {
                ...wrapper.vm.salesChannel,
                defaultMeasurementSystemId: 'other-system'
            }
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.salesChannel.defaultLengthUnitId).toBeNull();
        expect(wrapper.vm.salesChannel.defaultMassUnitId).toBeNull();

        await wrapper.setData({
            salesChannel: {
                ...wrapper.vm.salesChannel,
                defaultMeasurementSystemId: 'default-system'
            }
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.salesChannel.defaultLengthUnitId).toBe('default-length');
        expect(wrapper.vm.salesChannel.defaultMassUnitId).toBe('default-mass');
    });
});
