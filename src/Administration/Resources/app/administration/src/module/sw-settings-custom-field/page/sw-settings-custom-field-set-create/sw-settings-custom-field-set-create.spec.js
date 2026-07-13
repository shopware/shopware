/**
 * @sw-package framework
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
                    $t(key) {
                        const translations = {
                            'global.default.error': 'translation',
                            'global.error-codes.c1051bb4-d103-4f74-8988-acbcafc7fdc3': 'translation',
                            'sw-settings-custom-field.set.detail.messageNameNotUnique': 'translation',
                        };

                        return translations[key] ?? key;
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
                    'sw-page': {
                        template: '<div><slot name="content"></slot></div>',
                    },
                    'mt-card': {
                        props: ['title'],
                        template: '<div class="mt-card" :data-title="title"><slot></slot></div>',
                    },
                    'mt-empty-state': true,
                    'sw-custom-field-set-detail-base': true,
                    'sw-button-process': true,
                    'sw-card-view': {
                        template: '<div><slot></slot></div>',
                    },
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

    it('should render the save-first custom fields empty state', async () => {
        await flushPromises();

        const card = wrapper.get('.sw-settings-custom-field-set-create__custom-fields-card');
        const emptyState = wrapper.get('.sw-settings-custom-field-set-create__custom-fields-empty-state');

        expect(card.attributes('data-title')).toBe('sw-settings-custom-field.set.detail.titleCardCustomFields');
        expect(emptyState.attributes('icon')).toBe('regular-bars-square');
        expect(emptyState.attributes('headline')).toBe('sw-settings-custom-field.set.detail.createStateTitle');
        expect(emptyState.attributes('description')).toBe('sw-settings-custom-field.set.detail.createStateDescription');
        expect(emptyState.attributes('centered')).toBeUndefined();
        expect(emptyState.attributes('role')).toBe('status');
        expect(emptyState.attributes('aria-live')).toBe('polite');
        expect(emptyState.attributes('aria-atomic')).toBe('true');
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
});
