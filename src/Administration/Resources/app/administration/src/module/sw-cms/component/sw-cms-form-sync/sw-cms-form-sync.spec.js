/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';
import { setupCmsEnvironment } from '../../test-utils';

const { set } = Shopware.Utils.object;

const categoryDetailCmsRoute = {
    name: 'sw.category.detail.cms',
};

const defaultElementId = 'test-slot-id';

const getDefaultElement = () => ({
    id: defaultElementId,
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
});

async function createWrapper(props = {}, options = {}, route = categoryDetailCmsRoute) {
    const defaultProps = {
        element: getDefaultElement(),
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
    let initialLanguageId;
    let initialLanguage;

    beforeAll(() => {
        setupCmsEnvironment();
    });

    beforeEach(() => {
        initialLanguageId = Shopware.Store.get('context').api.languageId;
        initialLanguage = Shopware.Store.get('context').api.language;
        Shopware.Store.get('swCategoryDetail').$reset();
    });

    afterEach(() => {
        Shopware.Store.get('context').api.languageId = initialLanguageId;
        Shopware.Store.get('context').api.language = initialLanguage;
    });

    it('should not sync field changes if contentEntity is not provided', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.createWatcher = jest.fn();

        set(wrapper.vm.element.config, 'content.value', 'updated content');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.contentEntity).toBeNull();
        expect(wrapper.vm.createWatcher).toHaveBeenCalledTimes(0);
    });

    it('should sync field changes to contentEntity.slotConfig', async () => {
        const contentEntity = {
            slotConfig: {},
        };
        Shopware.Store.get('swCategoryDetail').category = contentEntity;
        const wrapper = await createWrapper();

        set(wrapper.vm.element.config, 'content.value', 'updated content');
        await wrapper.vm.$nextTick();

        expect(contentEntity.slotConfig[defaultElementId]).toBeDefined();
        expect(contentEntity.slotConfig[defaultElementId].content).toStrictEqual({
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

        expect(contentEntity.slotConfig[wrapper.vm.element.id].content).toStrictEqual({
            value: 'new content',
            source: 'static',
        });
    });

    it('should skip initial setup when oldConfig is undefined', async () => {
        const contentEntity = {
            slotConfig: {},
        };
        Shopware.Store.get('swCategoryDetail').category = contentEntity;

        await createWrapper({
            element: {
                id: 'initial-slot-id',
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

        expect(contentEntity.slotConfig[defaultElementId]).toBeUndefined();
    });

    it('should handle contentEntity without initial slotConfig', async () => {
        const contentEntity = {};
        Shopware.Store.get('swCategoryDetail').category = contentEntity;

        const wrapper = await createWrapper();

        set(wrapper.vm.element.config, 'content.value', 'new value');
        await wrapper.vm.$nextTick();

        expect(contentEntity.slotConfig).toBeDefined();
        expect(contentEntity.slotConfig[defaultElementId].content.value).toBe('new value');
    });

    it('should not sync inherited entity-level slotConfig as local child override', async () => {
        const contentEntity = {
            slotConfig: {},
            translations: [
                {
                    languageId: 'parent-language-id',
                    slotConfig: {
                        [defaultElementId]: {
                            content: {
                                value: 'default - override',
                            },
                        },
                    },
                },
            ],
        };

        Shopware.Store.get('swCategoryDetail').category = contentEntity;
        Shopware.Store.get('context').api.languageId = 'child-language-id';
        Shopware.Store.get('context').api.language = { parentId: 'parent-language-id' };

        const wrapper = await createWrapper();

        wrapper.vm.fieldChangeHandler('content', {
            value: 'default - override',
        });

        expect(contentEntity.slotConfig[defaultElementId]).toBeUndefined();
    });

    it('should not sync base CMS config as local override for the current language', async () => {
        const contentEntity = {
            slotConfig: {},
            translations: [],
        };

        Shopware.Store.get('swCategoryDetail').category = contentEntity;

        const wrapper = await createWrapper({
            element: {
                ...getDefaultElement(),
                translated: {
                    config: {
                        content: {
                            value: 'base content',
                        },
                    },
                },
            },
        });

        wrapper.vm.fieldChangeHandler('content', {
            value: 'base content',
        });

        expect(contentEntity.slotConfig[defaultElementId]).toBeUndefined();
    });
});
