import { mount } from '@vue/test-utils';

/**
 * @sw-package inventory
 */

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-settings-tag-detail-modal', {
            sync: true,
        }),
        {
            global: {
                renderStubDefaultSlot: true,
                provide: {
                    repositoryFactory: {
                        create: () => ({
                            create: () => {
                                return {
                                    isNew: () => true,
                                };
                            },

                            save: jest.fn(() => Promise.resolve()),
                        }),
                    },
                    syncService: {
                        sync: jest.fn(),
                    },
                    acl: {
                        can: () => {
                            return true;
                        },
                    },
                },
                stubs: {
                    'sw-modal': true,
                    'sw-tabs': await wrapTestComponent('sw-tabs', {
                        sync: true,
                    }),
                    'sw-tabs-item': true,
                    'sw-text-field': true,
                    'sw-settings-tag-detail-assignments': true,
                    'sw-tabs-deprecated': true,
                    'mt-tabs': {
                        name: 'mt-tabs',
                        props: {
                            items: {
                                type: Array,
                                required: true,
                            },
                            positionIdentifier: {
                                type: String,
                                default: null,
                            },
                            defaultItem: {
                                type: String,
                                default: '',
                            },
                            small: {
                                type: Boolean,
                                default: true,
                            },
                        },
                        emits: ['new-item-active'],
                        template: '<div class="mt-tabs-stub"></div>',
                    },
                },
            },
        },
    );
}

describe('module/sw-settings-tag/component/sw-settings-tag-detail-modal', () => {
    beforeEach(() => {
        global.activeFeatureFlags = [];
    });

    it('should render mt-tabs when the major feature flag is enabled', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];

        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const tabs = wrapper.getComponent('.mt-tabs-stub');
        expect(tabs.props('positionIdentifier')).toBe('sw-settings-tag-detail-modal');
        expect(tabs.props('defaultItem')).toBe('general');
        expect(tabs.props('items')).toEqual([
            expect.objectContaining({ label: 'sw-settings-tag.detail.generalTab', name: 'general' }),
            expect.objectContaining({ label: 'sw-settings-tag.detail.assignmentsTab', name: 'assignments' }),
        ]);

        await tabs.vm.$emit('new-item-active', 'assignments');
        expect(wrapper.vm.initialTab).toBe('assignments');
    });

    it('should set tag, to be added and to be deleted on create', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.tag).not.toBeNull();

        const initialAssignments = {
            products: {},
            media: {},
            newsletterRecipients: {},
            categories: {},
            customers: {},
            orders: {},
            landingPages: {},
            rules: {},
            shippingMethods: {},
        };

        expect(wrapper.vm.assignmentsToBeAdded).toEqual(initialAssignments);
        expect(wrapper.vm.assignmentsToBeDeleted).toEqual(initialAssignments);
    });

    it('should emit event on save', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        await wrapper.setData({
            assignmentsToBeDeleted: {
                products: { '0b7957f43b9b489fb7bc02a0a233274e': {} },
            },
        });

        await wrapper.vm.onSave();

        expect(wrapper.vm.syncService.sync).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.tagRepository.save).toHaveBeenCalledTimes(1);

        const onSaveEvents = wrapper.emitted('finish');
        expect(onSaveEvents).toHaveLength(1);
    });

    it('should emit event on cancel', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        wrapper.vm.onCancel();

        const onCancelEvents = wrapper.emitted('close');
        expect(onCancelEvents).toHaveLength(1);
    });

    it('should increase and decrease counts from to be added and to be deleted', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        await wrapper.setProps({
            counts: { products: 7 },
        });

        expect(wrapper.vm.computedCounts.products).toBe(7);

        await wrapper.setData({
            assignmentsToBeDeleted: {
                products: { a: {}, b: {} },
                invalid: { a: {} },
            },
            assignmentsToBeAdded: {
                products: { a: {}, b: {}, c: {}, d: {} },
                invalid: { a: {} },
            },
        });

        expect(wrapper.vm.computedCounts.products).toBe(9);
    });

    it('should add and remove assignments', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        await wrapper.setData({
            assignmentsToBeDeleted: {
                products: { a: { id: 'a' }, b: { id: 'b' } },
            },
            assignmentsToBeAdded: {
                products: { c: { id: 'c' }, d: { id: 'd' } },
            },
        });

        wrapper.vm.addAssignment('products', 'b', { id: 'b' });
        wrapper.vm.addAssignment('products', 'e', { id: 'e' });
        wrapper.vm.removeAssignment('products', 'd', { id: 'd' });
        wrapper.vm.removeAssignment('products', 'f', { id: 'f' });

        expect(wrapper.vm.assignmentsToBeDeleted.products).toEqual({
            a: { id: 'a' },
            f: { id: 'f' },
        });
        expect(wrapper.vm.assignmentsToBeAdded.products).toEqual({
            c: { id: 'c' },
            e: { id: 'e' },
        });
    });
});
