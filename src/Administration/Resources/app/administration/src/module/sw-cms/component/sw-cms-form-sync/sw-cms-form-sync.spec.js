/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';
import '../../mixin/sw-cms-state.mixin';

const { set } = Shopware.Utils.object;

const categoryDetailCmsRoute = {
    name: 'sw.category.detail.cms',
};

async function createWrapper(props = {}, options = {}, route = categoryDetailCmsRoute) {
    const defaultProps = {
        element: {
            id: 'test-slot-id',
            type: 'text',
            config: {
                content: {
                    value: 'test content',
                },
                verticalAlign: {
                    value: null,
                },
            },
            translated: {
                config: {
                    content: {
                        value: 'base content',
                    },
                },
            },
        },
        ...props,
    };

    return mount(
        await wrapTestComponent('sw-cms-form-sync', {
            sync: true,
        }),
        {
            global: {
                provide: {
                    cmsService: {
                        getCmsElementRegistry: () => ({
                            text: {
                                defaultConfig: {
                                    content: {
                                        value: '',
                                    },
                                    verticalAlign: {
                                        value: null,
                                    },
                                },
                            },
                        }),
                    },
                },
                mocks: {
                    $route: route,
                },
            },
            props: defaultProps,
            ...options,
        },
    );
}

describe('src/module/sw-cms/component/sw-cms-form-sync', () => {
    it('should not sync field changes if contentEntity is not provided', async () => {
        const wrapper = await createWrapper();

        set(wrapper.vm.element.config, 'content.value', 'updated content');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.contentEntity).toBeNull();
    });

    it('should sync field changes to contentEntity.slotConfig', async () => {
        const contentEntity = {
            slotConfig: {},
        };
        Shopware.Store.get('swCategoryDetail').category = contentEntity;
        const wrapper = await createWrapper();

        set(wrapper.vm.element.config, 'content.value', 'updated content');
        await wrapper.vm.$nextTick();

        expect(contentEntity.slotConfig['test-slot-id']).toBeDefined();
        expect(contentEntity.slotConfig['test-slot-id'].content).toStrictEqual({
            value: 'updated content',
        });
    });

    it('should sync nested field changes', async () => {
        const contentEntity = {
            slotConfig: {},
        };
        Shopware.Store.get('swCategoryDetail').category = contentEntity;

        const wrapper = await createWrapper();

        set(wrapper.vm.element.config, 'content', {
            value: 'new content',
            source: 'static',
        });
        await wrapper.vm.$nextTick();

        expect(contentEntity.slotConfig['test-slot-id'].content).toStrictEqual({
            value: 'new content',
            source: 'static',
        });
    });

    it('should skip initial setup when oldConfig is undefined', async () => {
        const contentEntity = {
            slotConfig: {},
        }
        Shopware.Store.get('swCategoryDetail').category = contentEntity;

        await createWrapper({
            element: {
                id: 'test-slot-id',
                type: 'text',
                config: {
                    content: {
                        value: 'initial',
                    },
                },
                translated: {
                    config: {},
                },
            },
        });

        expect(contentEntity.slotConfig['test-slot-id']).toBeUndefined();
    });

    it('should handle contentEntity without initial slotConfig', async () => {
        const contentEntity = {};
        Shopware.Store.get('swCategoryDetail').category = contentEntity;

        const wrapper = await createWrapper();

        set(wrapper.vm.element.config, 'content.value', 'new value');
        await wrapper.vm.$nextTick();

        expect(contentEntity.slotConfig).toBeDefined();
        expect(contentEntity.slotConfig['test-slot-id'].content.value).toBe('new value');
    });
});
