/**
 * @package content
 * @group disabledCompat
 */
import { mount } from '@vue/test-utils';
import 'src/module/sw-media/mixin/media-sidebar-modal.mixin';

const itemMock = (options = {}) => {
    const itemOptions = {
        getEntityName: () => { return 'media'; },
        id: '4a12jd3kki9yyy765gkn5hdb',
        fileName: 'demo.jpg',
        avatarUsers: [],
        categories: [],
        productManufacturers: [],
        productMedia: [],
        mailTemplateMedia: [],
        documentBaseConfigs: [],
        paymentMethods: [],
        shippingMethods: [],
        ...options,
    };

    return Object.assign(itemOptions, options);
};

async function createWrapper(itemMockOptions, mediaServiceFunctions = {}, mediaRepositoryProvideFunctions = {}) {
    return mount(await wrapTestComponent('sw-media-quickinfo', { sync: true }), {
        global: {
            mocks: {
                $route: {
                    query: {
                        page: 1,
                        limit: 25,
                    },
                },
            },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        search: () => {
                            return Promise.resolve();
                        },
                        get: () => {
                            return Promise.resolve();
                        },
                        ...mediaRepositoryProvideFunctions,
                    }),
                },
                systemConfigApiService: {
                    getValues: () => {
                        return Promise.resolve({
                            'core.store.media.defaultEnableAugmentedReality': 'false',
                        });
                    },
                },
                mediaService: {
                    renameMedia: () => Promise.resolve(),
                    ...mediaServiceFunctions,
                },
                customFieldDataProviderService: {
                    getCustomFieldSets: () => Promise.resolve([]),
                },
            },
            stubs: {
                'sw-page': {
                    template: `
                        <div class="sw-page">
                            <slot name="smart-bar-actions"></slot>
                            <slot name="content">CONTENT</slot>
                            <slot></slot>
                        </div>`,
                },
                'sw-alert': true,
                'sw-icon': true,
                'sw-media-collapse': {
                    template: `
                        <div class="sw-media-quickinfo">
                            <slot name="content"></slot>
                        </div>`,
                },
                'sw-media-quickinfo-metadata-item': true,
                'sw-media-preview-v2': true,
                'sw-media-tag': true,
                'sw-custom-field-set-renderer': true,
                'sw-field-error': true,
                'sw-switch-field': await wrapTestComponent('sw-switch-field', { sync: true }),
                'sw-switch-field-deprecated': await wrapTestComponent('sw-switch-field-deprecated', { sync: true }),
                'sw-checkbox-field': await wrapTestComponent('sw-checkbox-field', { sync: true }),
                'sw-checkbox-field-deprecated': await wrapTestComponent('sw-checkbox-field-deprecated', { sync: true }),
                'sw-base-field': await wrapTestComponent('sw-base-field', { sync: true }),
                'sw-inherit-wrapper': await wrapTestComponent('sw-inherit-wrapper', { sync: true }),
                'sw-confirm-field': true,
                'sw-media-modal-replace': true,
                'sw-help-text': true,
                'sw-media-modal-delete': true,
                'sw-external-link': true,
                'sw-media-quickinfo-usage': true,
                'sw-media-modal-move': true,
                'sw-inheritance-switch': true,
                'sw-ai-copilot-badge': true,
            },
        },

        props: {
            item: itemMock(itemMockOptions),
            editable: true,
        },
    });
}

/**
 * @returns {[[object,boolean, boolean]]} [i][0] Array of options for the mockItem, [i][1] flag for if 'isSpatial', [i][2] flag for if 'isArReady'
 */
function provide2DMockOptions() {
    return [
        [
            {},
            false,
            false,
        ],
    ];
}

/**
 * @returns {[[object,boolean, boolean]]} [i][0] Array of options for the mockItem, [i][1] flag for if 'isSpatial', [i][2] flag for if 'isArReady'
 */
function provide3DMockOptions() {
    return [
        [
            {
                fileName: 'smth.glb',
                fileExtension: 'glb',
            },
            true,
            false,
        ],
        [
            {
                fileName: 'smth.glb',
                url: 'http://shopware.example.com/media/file/2b71335f118c4940b425c55352e69e44/media-1-three-d.glb',
            },
            true,
            true,
        ],
    ];
}

describe('module/sw-media/components/sw-media-quickinfo', () => {
    beforeEach(() => {
        global.activeAclRoles = [];
    });

    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should not be able to delete', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const deleteMenuItem = wrapper.find('.quickaction--delete');
        expect(deleteMenuItem.classes()).toContain('sw-media-sidebar__quickaction--disabled');
    });

    it('should be able to delete', async () => {
        global.activeAclRoles = ['media.deleter'];

        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const deleteMenuItem = wrapper.find('.quickaction--delete');
        expect(deleteMenuItem.classes()).not.toContain('sw-media-sidebar__quickaction--disabled');
    });

    it('should not be able to edit', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const editMenuItem = wrapper.find('.quickaction--move');
        expect(editMenuItem.classes()).toContain('sw-media-sidebar__quickaction--disabled');
    });

    it('should be able to edit', async () => {
        global.activeAclRoles = ['media.editor'];

        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const editMenuItem = wrapper.find('.quickaction--move');
        expect(editMenuItem.classes()).not.toContain('sw-media-sidebar__quickaction--disabled');
    });

    it.each([
        {
            status: 500,
            code: 'CONTENT__MEDIA_ILLEGAL_FILE_NAME',
        },
        {
            status: 500,
            code: 'CONTENT__MEDIA_EMPTY_FILE',
        },
    ])('should map error %p', async (error) => {
        global.activeAclRoles = ['media.editor'];

        const wrapper = await createWrapper(
            {},
            {
                // eslint-disable-next-line prefer-promise-reject-errors
                renameMedia: () => Promise.reject(
                    {
                        response: {
                            data: {
                                errors: [
                                    error,
                                ],
                            },
                        },
                    },
                ),
            },
        );
        await wrapper.vm.$nextTick();

        await wrapper.vm.onChangeFileName('newFileName');

        expect(wrapper.vm.fileNameError).toStrictEqual(error);
    });

    it.each([...provide2DMockOptions(), ...provide3DMockOptions()])('should display ar-ready toggle if item is a 3D file', async (mockOptions, isSpatial) => {
        global.activeAclRoles = ['media.editor'];

        const wrapper = await createWrapper(mockOptions);
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-media-sidebar__quickactions-switch.ar-ready-toggle').exists()).toBe(isSpatial);
    });

    it.each(provide3DMockOptions())('should trigger update:item event when toggle is changed', async (mockOptions, isSpatial) => {
        global.activeAclRoles = ['media.editor'];
        const mediaSaveMock = jest.fn();
        const mediaRepositoryFunctions = {
            save: mediaSaveMock,
        };

        const wrapper = await createWrapper(mockOptions, {}, mediaRepositoryFunctions);
        await wrapper.vm.$nextTick();

        const arToggle = wrapper.find('.sw-media-sidebar__quickactions-switch.ar-ready-toggle');
        expect(arToggle.exists()).toBe(isSpatial);

        const arToggleInput = wrapper.find('.sw-field--switch__input input');
        expect(arToggleInput.exists()).toBe(isSpatial);

        await arToggleInput.setChecked();
        expect(arToggleInput.element.checked).toBe(true);

        await arToggle.trigger('update');
        expect(wrapper.emitted('update:item')).toBeTruthy();
        expect(wrapper.emitted('update:item')[0][0]).toEqual(
            expect.objectContaining({
                config: {
                    spatial: {
                        arReady: true,
                        updatedAt: expect.any(Number),
                    },
                },
            }),
        );
    });

    it.each(provide3DMockOptions())('should check if object is AR ready when created and update ar toggle accordingly', async (mockOptions, isSpatial, isArReady) => {
        global.activeAclRoles = ['media.editor'];
        const mediaRepositoryGetMock = jest.fn().mockResolvedValue({
            config: {
                spatial: {
                    arReady: isArReady,
                },
            },
        });
        const mediaRepositoryFunctions = {
            get: mediaRepositoryGetMock,
        };

        const wrapper = await createWrapper(mockOptions, {}, mediaRepositoryFunctions);
        await wrapper.vm.$nextTick();

        const arToggle = wrapper.findComponent('.sw-media-sidebar__quickactions-switch.ar-ready-toggle');
        expect(arToggle.exists()).toBe(true);

        const arToggleInput = wrapper.find('.sw-field--switch__input input');
        expect(arToggleInput.exists()).toBe(true);

        expect(arToggleInput.element.checked).toBe(isArReady);
    });

    it('should build augmented reality tooltip', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const tooltip = wrapper.vm.buildAugmentedRealityTooltip('global.sw-media-media-item.tooltip.ar');
        expect(tooltip).toBe('global.sw-media-media-item.tooltip.ar');
    });
});

