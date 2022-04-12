import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-settings-tag/component/sw-settings-tag-detail-modal';

function createWrapper() {
    const localVue = createLocalVue();

    return shallowMount(Shopware.Component.build('sw-settings-tag-detail-modal'), {
        localVue,
        provide: {
            repositoryFactory: {
                create: () => ({
                    create: () => {
                        return {
                            isNew: () => true
                        };
                    },

                    save: jest.fn(() => Promise.resolve())
                })
            },
            syncService: {
                sync: jest.fn()
            },
            acl: {
                can: () => {
                    return true;
                }
            }
        },
        stubs: {
            'sw-modal': true,
            'sw-tabs': true
        }
    });
}

describe('module/sw-settings-tag/component/sw-settings-tag-detail-modal', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should set tag, to be added and to be deleted on create', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.tag).not.toEqual(null);

        const initialAssignments = {
            products: {},
            media: {},
            newsletterRecipients: {},
            categories: {},
            customers: {},
            orders: {},
            landingPages: {},
            rules: {},
            shippingMethods: {}
        };

        expect(wrapper.vm.assignmentsToBeAdded).toEqual(initialAssignments);
        expect(wrapper.vm.assignmentsToBeDeleted).toEqual(initialAssignments);
    });

    it('should emit event on save', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        await wrapper.setData({
            assignmentsToBeDeleted: {
                products: { '0b7957f43b9b489fb7bc02a0a233274e': {} }
            }
        });

        await wrapper.vm.onSave();

        expect(wrapper.vm.syncService.sync).toBeCalledTimes(1);
        expect(wrapper.vm.tagRepository.save).toBeCalledTimes(1);

        const onSaveEvents = wrapper.emitted('finish');
        expect(onSaveEvents.length).toBe(1);
    });

    it('should emit event on cancel', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        wrapper.vm.onCancel();

        const onCancelEvents = wrapper.emitted('close');
        expect(onCancelEvents.length).toBe(1);
    });
});
