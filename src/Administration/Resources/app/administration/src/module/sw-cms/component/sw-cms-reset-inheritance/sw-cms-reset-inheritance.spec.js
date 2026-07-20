/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';
import { setupCmsEnvironment } from '../../test-utils';

async function createWrapper(props = {}) {
    const defaultProps = {
        contentEntity: {
            slotConfig: {
                'slot-1': {
                    testField: {
                        value: 'override-value',
                    },
                },
            },
        },
        ...props,
    };

    return mount(
        await wrapTestComponent('sw-cms-reset-inheritance', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'mt-icon': {
                        template: '<i class="mt-icon" :name="name"></i>',
                        props: [
                            'name',
                            'size',
                            'color',
                        ],
                    },
                    'mt-link': {
                        template: '<button class="mt-link" @click="$emit(\'click\')"><slot /></button>',
                        props: [
                            'variant',
                            'as',
                        ],
                    },
                    'sw-confirm-modal': {
                        template: `
                            <div class="sw-confirm-modal">
                                <button class="confirm-btn" @click="$emit('confirm')">Confirm</button>
                                <button class="cancel-btn" @click="$emit('cancel')">Cancel</button>
                                <button class="close-btn" @click="$emit('close')">Close</button>
                            </div>
                        `,
                        props: [
                            'type',
                            'text',
                            'title',
                            'textConfirm',
                        ],
                    },
                },
                mocks: {
                    $t: (key) => key,
                    $route: {
                        name: 'sw.category.detail.cms',
                    },
                },
            },
            props: defaultProps,
        },
    );
}

describe('src/module/sw-cms/component/sw-cms-reset-inheritance', () => {
    beforeAll(async () => {
        await setupCmsEnvironment();
    });

    afterEach(() => {
        Shopware.Store.get('cmsPage').$reset();
    });

    describe('data properties', () => {
        it('should initialize with showModal as false', async () => {
            const wrapper = await createWrapper();

            expect(wrapper.vm.showModal).toBe(false);
        });
    });

    describe('computed properties', () => {
        it('should compute hasOverrides as true when slotConfig is not empty', async () => {
            Shopware.Store.get('swCategoryDetail').category = {
                slotConfig: {
                    'slot-1': {
                        testField: { value: 'test' },
                    },
                },
            };

            const wrapper = await createWrapper();

            expect(wrapper.vm.hasOverrides).toBe(true);
        });

        it('should compute hasOverrides as false when slotConfig is empty', async () => {
            Shopware.Store.get('swCategoryDetail').category = {
                slotConfig: {},
            };

            const wrapper = await createWrapper();

            expect(wrapper.vm.hasOverrides).toBe(false);
        });

        it('should compute hasOverrides as false when slotConfig is undefined', async () => {
            const wrapper = await createWrapper();

            expect(wrapper.vm.hasOverrides).toBe(false);
        });
    });

    describe('conditional rendering', () => {
        it('should render the component when hasOverrides is true', async () => {
            Shopware.Store.get('swCategoryDetail').category = {
                slotConfig: {
                    'slot-1': { testField: { value: 'test' } },
                },
            };
            const wrapper = await createWrapper();

            expect(wrapper.find('.sw-category-layout-card__desc-reset').exists()).toBe(true);
        });

        it('should not render the component when hasOverrides is false', async () => {
            Shopware.Store.get('swCategoryDetail').category = {
                slotConfig: {},
            };
            const wrapper = await createWrapper();

            expect(wrapper.find('.sw-category-layout-card__desc-reset').exists()).toBe(false);
        });
    });

    it('should unset slotConfig from contentEntity', async () => {
        const contentEntity = {
            slotConfig: {
                'slot-1': { testField: { value: 'test' } },
            },
        };
        Shopware.Store.get('swCategoryDetail').category = contentEntity;
        const wrapper = await createWrapper();

        await wrapper.vm.onConfirm();
        await wrapper.vm.$nextTick();

        expect(contentEntity.slotConfig).toBeNull();
    });

    it('should reset slot configs to base values', async () => {
        const slot1 = {
            id: 'slot-1',
            config: {
                testField: { value: 'overridden-value' },
            },
            getOrigin: () => ({
                config: {
                    testField: { value: 'base-value' },
                },
            }),
        };

        Shopware.Store.get('cmsPage').setCurrentPage({
            sections: [
                {
                    blocks: [
                        {
                            slots: [slot1],
                        },
                    ],
                },
            ],
        });

        const wrapper = await createWrapper();

        wrapper.vm.resetSlotOverrides();

        await wrapper.vm.$nextTick();

        expect(slot1.config).toStrictEqual({
            testField: { value: 'base-value' },
        });
    });

    it('should handle multiple sections, blocks, and slots', async () => {
        const slot1 = {
            id: 'slot-1',
            config: { field1: { value: 'override1' } },
            getOrigin: () => ({ config: { field1: { value: 'base1' } } }),
        };

        const slot2 = {
            id: 'slot-2',
            config: { field2: { value: 'override2' } },
            getOrigin: () => ({ config: { field2: { value: 'base2' } } }),
        };

        Shopware.Store.get('cmsPage').setCurrentPage({
            sections: [
                {
                    blocks: [
                        { slots: [slot1] },
                        { slots: [slot2] },
                    ],
                },
            ],
        });

        const wrapper = await createWrapper();

        wrapper.vm.resetSlotOverrides();

        expect(slot1.config).toStrictEqual({ field1: { value: 'base1' } });
        expect(slot2.config).toStrictEqual({ field2: { value: 'base2' } });
    });

    it('should handle empty sections', async () => {
        Shopware.Store.get('cmsPage').setCurrentPage({
            sections: [],
        });

        const wrapper = await createWrapper();

        expect(() => wrapper.vm.resetSlotOverrides()).not.toThrow();
    });

    it('should handle sections without blocks', async () => {
        Shopware.Store.get('cmsPage').setCurrentPage({
            sections: [{ blocks: null }],
        });

        const wrapper = await createWrapper();

        expect(() => wrapper.vm.resetSlotOverrides()).not.toThrow();
    });

    it('should handle blocks without slots', async () => {
        Shopware.Store.get('cmsPage').setCurrentPage({
            sections: [
                {
                    blocks: [{ slots: null }],
                },
            ],
        });

        const wrapper = await createWrapper();

        expect(() => wrapper.vm.resetSlotOverrides()).not.toThrow();
    });
});
