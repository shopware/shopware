/**
 * @package services-settings
 */
import { mount } from '@vue/test-utils';
import 'src/app/mixin/notification.mixin';

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-settings-custom-field-set-create', {
            sync: true,
        }),
        {
            global: {
                renderStubDefaultSlot: true,
                mocks: {
                    $tc() {
                        return 'translation';
                    },
                },
                provide: {
                    repositoryFactory: {
                        create(repositoryName) {
                            if (repositoryName === 'custom_field') {
                                return {};
                            }

                            return {
                                get() {
                                    return Promise.resolve({});
                                },
                                create() {
                                    return Promise.resolve({});
                                },
                                search() {
                                    return Promise.resolve({
                                        length: 0,
                                    });
                                },
                            };
                        },
                    },
                },
                stubs: {
                    'sw-page': true,
                    'sw-empty-state': true,
                    'sw-custom-field-set-detail-base': true,
                    'sw-button': true,
                    'sw-button-process': true,
                    'sw-card': true,
                    'sw-card-view': true,
                    'sw-skeleton': true,
                },
            },
        },
    );
}

describe('src/module/sw-settings-custom-field/page/sw-settings-custom-field-set-create', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should handle route enter', async () => {
        const next = jest.fn();
        const params = {};
        wrapper.vm.$options.beforeRouteEnter(
            {
                name: 'sw.settings.custom.field.create',
                params,
            },
            {},
            next,
        );

        expect(next).toHaveBeenCalledTimes(1);
        expect(params.hasOwnProperty('id')).toBeTruthy();
    });

    it('should finish save', async () => {
        wrapper.vm.$router.push = jest.fn();
        wrapper.vm.saveFinish();

        expect(wrapper.vm.$router.push).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.$router.push).toHaveBeenCalledWith({
            name: 'sw.settings.custom.field.detail',
            params: {
                id: wrapper.vm.setId,
            },
        });
    });

    it('should create technical name error for empty set', async () => {
        wrapper.vm.set.name = '';
        wrapper.vm.onSave();

        expect(wrapper.vm.technicalNameError).toBeTruthy();
        expect(wrapper.vm.isLoading).toBeFalsy();
        expect(wrapper.vm.technicalNameError.hasOwnProperty('detail')).toBeTruthy();
        expect(wrapper.vm.technicalNameError.detail).toBe('translation');
    });

    it('should create name not unique notification', async () => {
        wrapper.vm.createNotificationError = jest.fn();
        wrapper.vm.createNameNotUniqueNotification();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            title: 'translation',
            message: 'translation',
        });
        expect(wrapper.vm.technicalNameError).toBeTruthy();
        expect(wrapper.vm.technicalNameError.hasOwnProperty('detail')).toBeTruthy();
        expect(wrapper.vm.technicalNameError.detail).toBe('translation');
    });

    it('should save', async () => {
        wrapper.vm.$super = jest.fn();
        wrapper.vm.onSave();
        await flushPromises();

        expect(wrapper.vm.$super).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.$super).toHaveBeenCalledWith('onSave');
    });
});
