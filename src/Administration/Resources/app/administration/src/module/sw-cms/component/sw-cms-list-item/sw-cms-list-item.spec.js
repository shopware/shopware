/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-cms-list-item', {
            sync: true,
        }),
        {
            props: {
                page: {
                    name: 'My custom layout',
                    type: 'product_list',
                    translated: {
                        name: 'some-name',
                    },
                    sections: [
                        {
                            name: 'Section 1',
                            blocks: [
                                {
                                    name: 'Test block',
                                    type: 'product-listing',
                                    slots: [],
                                },
                            ],
                        },
                    ],
                },
            },
            global: {
                stubs: {
                    'sw-icon': true,
                },
            },
        },
    );
}

describe('module/sw-cms/component/sw-cms-list-item', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should not render default item layout background image with insufficient data', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            page: {
                name: 'no content',
                type: 'product_list',
                translated: {
                    name: 'no content',
                },
                sections: [],
            },
        });

        expect(wrapper.vm.defaultItemLayoutAssetBackground).toBeNull();
    });

    const previewMediaDataProvider = [
        [
            'when previewMedia is set',
            {
                name: 'some name',
                translated: {
                    name: 'some name',
                },
                previewMedia: {
                    id: 'media-id',
                    url: 'media-url',
                },
            },
            {
                'background-image': 'url(media-url)',
                'background-size': 'cover',
            },
        ],
        [
            'when page is locked and type is not page',
            {
                name: 'some name',
                type: 'product_list',
                translated: {
                    name: 'some name',
                },
                locked: true,
            },
            {
                'background-image': 'url(administration/static/img/cms/default_preview_product_list.jpg)',
            },
        ],
        [
            'with defaultItemLayoutAssetBackground',
            {
                name: 'some name',
                type: 'product_list',
                translated: {
                    name: 'some name',
                },
                sections: [
                    {
                        type: 'product_listing',
                    },
                ],
            },
            {
                'background-image': 'url(administration/static/img/cms/preview_product_list_product_listing.png)',
                'background-size': 'cover',
            },
        ],
        [
            'without defaultItemLayoutAssetBackground',
            {
                name: 'some name',
                type: 'product_list',
                translated: {
                    name: 'some name',
                },
                sections: [],
            },
            null,
        ],
    ];
    it.each(previewMediaDataProvider)('should render previewMedia correctly %s', async (caseName, page, expected) => {
        const wrapper = await createWrapper();
        await wrapper.setProps({ page });

        expect(wrapper.vm.previewMedia).toStrictEqual(expected);
    });

    const eventEmitterDataProvider = [
        [
            'preview-image-change',
            'onChangePreviewImage',
            true,
            true,
        ],
        [
            'preview-image-change',
            'onChangePreviewImage',
            false,
            true,
        ],
        [
            'on-item-click',
            'onElementClick',
            true,
            false,
        ] /** @deprecated tag:v6.7.0 - `on-item-click` will be removed */,
        [
            'on-item-click',
            'onElementClick',
            false,
            true,
        ] /** @deprecated tag:v6.7.0 - `on-item-click` will be removed */,
        [
            'element-click',
            'onElementClick',
            true,
            false,
        ],
        [
            'element-click',
            'onElementClick',
            false,
            true,
        ],
        [
            'item-click',
            'onItemClick',
            true,
            false,
        ],
        [
            'item-click',
            'onItemClick',
            false,
            true,
        ],
        [
            'cms-page-delete',
            'onDelete',
            true,
            true,
        ],
        [
            'cms-page-delete',
            'onDelete',
            false,
            true,
        ],
    ];
    it.each(eventEmitterDataProvider)(
        'should emit the %s event %s, when enabled [disabled: %s]',
        async (eventName, method, disabled, expectedHasBeenEmitted) => {
            const wrapper = await createWrapper();
            await wrapper.setProps({ disabled });

            wrapper.vm[method]();

            expect(!!wrapper.emitted()?.[eventName]).toBe(expectedHasBeenEmitted);
        },
    );

    it('should remove preview image and save on onRemovePreviewImage call', async () => {
        const wrapper = await createWrapper();

        const saveSpy = jest.fn();
        const page = {
            previewMedia: {
                id: 'media-id',
                url: 'media-url',
            },
            previewMediaId: 'media-id',
            save: saveSpy,
        };

        wrapper.vm.onRemovePreviewImage(page);

        expect(page.previewMediaId).toBeUndefined();
        expect(page.previewMedia).toBeUndefined();
        expect(saveSpy).toHaveBeenCalled();
    });

    it('should display whether the cms-page is set as default', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-cms-list-item__is-default').exists()).toBe(false);

        await wrapper.setProps({ isDefault: true });
        expect(wrapper.find('.sw-cms-list-item__is-default').text()).toBe(
            'sw-cms.components.cmsListItem.defaultLayoutProductList',
        );

        await wrapper.setProps({ isDefault: false });
        expect(wrapper.find('.sw-cms-list-item__is-default').exists()).toBe(false);
    });
});
