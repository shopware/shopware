/**
 * @package system-settings
 */
import { config, mount } from '@vue/test-utils';
import { createRouter, createWebHashHistory } from 'vue-router';

describe('src/module/sw-bulk-edit/page/sw-bulk-edit-customer', () => {
    let wrapper;
    let routes;

    async function createWrapper(isResponseError = false) {
        // delete global $router and $routes mocks
        delete config.global.mocks.$router;
        delete config.global.mocks.$route;

        const router = createRouter({
            history: createWebHashHistory(),
            routes,
        });
        router.push('/');
        await router.isReady();

        return mount(await wrapTestComponent('sw-bulk-edit-customer', { sync: true }), {
            global: {
                plugins: [
                    router,
                ],
                stubs: {
                    'sw-page': await wrapTestComponent('sw-page'),
                    'sw-loader': await wrapTestComponent('sw-loader'),
                    'sw-button': await wrapTestComponent('sw-button'),
                    'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated', { sync: true }),
                    'sw-select-field': await wrapTestComponent('sw-select-field'),
                    'sw-bulk-edit-custom-fields': await wrapTestComponent('sw-bulk-edit-custom-fields'),
                    'sw-bulk-edit-change-type-field-renderer': await wrapTestComponent('sw-bulk-edit-change-type-field-renderer'),
                    'sw-bulk-edit-form-field-renderer': await wrapTestComponent('sw-bulk-edit-form-field-renderer'),
                    'sw-bulk-edit-change-type': await wrapTestComponent('sw-bulk-edit-change-type'),
                    'sw-form-field-renderer': await wrapTestComponent('sw-form-field-renderer'),
                    'sw-empty-state': await wrapTestComponent('sw-empty-state'),
                    'sw-button-process': await wrapTestComponent('sw-button-process'),
                    'sw-card': await wrapTestComponent('sw-card'),
                    'sw-select-base': await wrapTestComponent('sw-select-base'),
                    'sw-single-select': await wrapTestComponent('sw-single-select'),
                    'sw-number-field': await wrapTestComponent('sw-number-field'),
                    'sw-switch-field': await wrapTestComponent('sw-switch-field'),
                    'sw-text-field': await wrapTestComponent('sw-text-field'),
                    'sw-textarea-field': await wrapTestComponent('sw-textarea-field'),
                    'sw-checkbox-field': await wrapTestComponent('sw-checkbox-field'),
                    'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                    'sw-block-field': await wrapTestComponent('sw-block-field'),
                    'sw-base-field': await wrapTestComponent('sw-base-field'),
                    'sw-container': await wrapTestComponent('sw-container'),
                    'sw-field-error': await wrapTestComponent('sw-field-error'),
                    'sw-entity-single-select': await wrapTestComponent('sw-entity-single-select'),
                    'sw-card-view': await wrapTestComponent('sw-card-view'),
                    'sw-custom-field-set-renderer': true,
                    'sw-text-editor-toolbar': true,
                    'sw-app-actions': true,
                    'sw-search-bar': true,
                    'sw-datepicker': true,
                    'sw-text-editor': true,
                    'sw-language-switch': true,
                    'sw-notification-center': true,
                    'sw-icon': true,
                    'sw-help-text': true,
                    'sw-alert': true,
                    'sw-label': true,
                    'sw-tabs': await wrapTestComponent('sw-tabs'),
                    'sw-tabs-item': await wrapTestComponent('sw-tabs-item'),
                    'sw-extension-component-section': true,
                    'sw-help-center': true,
                    'sw-ignore-class': true,
                    'sw-entity-tag-select': true,
                    'sw-error-summary': true,
                    'sw-bulk-edit-save-modal-error': await wrapTestComponent('sw-bulk-edit-save-modal-error', { sync: true }),
                    'sw-bulk-edit-save-modal-process': await wrapTestComponent('sw-bulk-edit-save-modal-process', { sync: true }),
                    'sw-bulk-edit-save-modal-success': await wrapTestComponent('sw-bulk-edit-save-modal-success', { sync: true }),
                    'sw-bulk-edit-save-modal-confirm': await wrapTestComponent('sw-bulk-edit-save-modal-confirm', { sync: true }),
                    'sw-bulk-edit-save-modal': await wrapTestComponent('sw-bulk-edit-save-modal', { sync: true }),
                },
                provide: {
                    validationService: {},
                    orderDocumentApiService: {},
                    repositoryFactory: {
                        create: () => {
                            return {
                                create: (entity) => {
                                    if (entity === 'custom_field_set') {
                                        return {
                                            search: () => Promise.resolve([{ id: 'field-set-id-1' }]),
                                            get: () => Promise.resolve({ id: '' }),
                                        };
                                    }

                                    return {
                                        id: '1a2b3c',
                                        name: 'Test Customer',
                                    };
                                },
                                search: () => Promise.resolve([
                                    {
                                        id: '1',
                                        name: 'customer 1',
                                    },
                                    {
                                        id: '2',
                                        name: 'customer 2',
                                    },
                                ]),
                                get: () => Promise.resolve({
                                    id: 1,
                                    name: 'Customer',
                                }),
                                searchIds: () => Promise.resolve([
                                    {
                                        data: [1],
                                        total: 1,
                                    },
                                ]),
                            };
                        },
                    },
                    bulkEditApiFactory: {
                        getHandler: () => {
                            return {
                                bulkEdit: (selectedIds) => {
                                    if (isResponseError) {
                                        return Promise.reject(new Error('error occurred'));
                                    }

                                    if (selectedIds.length === 0) {
                                        return Promise.reject();
                                    }

                                    return Promise.resolve();
                                },

                                bulkEditRequestedGroup: (selectedIds) => {
                                    if (isResponseError) {
                                        return Promise.reject(new Error('error occurred'));
                                    }

                                    if (selectedIds.length === 0) {
                                        return Promise.reject();
                                    }

                                    return Promise.resolve();
                                },
                            };
                        },
                    },
                    shortcutService: {
                        startEventListener: () => {},
                        stopEventListener: () => {},
                    },
                },
            },
            props: {
                title: 'Foo bar',
            },
            attachTo: document.body,
        });
    }

    beforeAll(async () => {
        routes = [
            {
                name: 'sw.bulk.edit.customer',
                path: '/index',
            },
            {
                name: 'sw.bulk.edit.customer.save',
                path: '',
                component: await wrapTestComponent('sw-bulk-edit-save-modal'),
                meta: {
                    $module: {
                        title: 'sw-bulk-edit-customer.general.mainMenuTitle',
                    },
                },
                redirect: {
                    name: 'sw.bulk.edit.customer.save.confirm',
                },
                children: [
                    {
                        name: 'sw.bulk.edit.customer.save.confirm',
                        path: '/confirm',
                        component: await wrapTestComponent('sw-bulk-edit-save-modal-confirm'),
                        meta: {
                            $module: {
                                title: 'sw-bulk-edit-customer.general.mainMenuTitle',
                            },
                        },
                    },
                    {
                        name: 'sw.bulk.edit.customer.save.process',
                        path: '/process',
                        component: await wrapTestComponent('sw-bulk-edit-save-modal-process'),
                        meta: {
                            $module: {
                                title: 'sw-bulk-edit-customer.general.mainMenuTitle',
                            },
                        },
                    },
                    {
                        name: 'sw.bulk.edit.customer.save.success',
                        path: '/success',
                        component: await wrapTestComponent('sw-bulk-edit-save-modal-success'),
                        meta: {
                            $module: {
                                title: 'sw-bulk-edit-customer.general.mainMenuTitle',
                            },
                        },
                    },
                    {
                        name: 'sw.bulk.edit.customer.save.error',
                        path: '/error',
                        component: await wrapTestComponent('sw-bulk-edit-save-modal-error'),
                        meta: {
                            $module: {
                                title: 'sw-bulk-edit-customer.general.mainMenuTitle',
                            },
                        },
                    },
                ],
            },
        ];
    });

    beforeEach(async () => {
        const mockResponses = global.repositoryFactoryMock.responses;
        mockResponses.addResponse({
            method: 'post',
            url: '/search/custom-field-set',
            status: 200,
            response: {
                data: [
                    {
                        id: Shopware.Utils.createId(),
                        attributes: {
                            id: Shopware.Utils.createId(),
                        },
                    },
                ],
            },
        });

        Shopware.State.commit('shopwareApps/setSelectedIds', [Shopware.Utils.createId()]);
    });

    it('should show all form fields', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-bulk-edit-change-field-renderer').exists()).toBeTruthy();
    });

    it('should be show empty state', async () => {
        wrapper = await createWrapper();

        Shopware.State.commit('shopwareApps/setSelectedIds', []);
        await wrapper.setData({
            isLoading: false,
        });

        const emptyState = wrapper.find('.sw-empty-state');
        expect(emptyState.find('.sw-empty-state__title').text()).toBe('sw-bulk-edit.customer.messageEmptyTitle');
    });

    it('should open confirm modal', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        await wrapper.find('.sw-bulk-edit-customer__save-action').trigger('click');

        await flushPromises();

        expect(wrapper.find('.sw-bulk-edit-save-modal-confirm').exists()).toBeTruthy();
        expect(wrapper.vm.$route.path).toBe('/confirm');
    });

    it('should close confirm modal', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        await wrapper.find('.sw-bulk-edit-customer__save-action').trigger('click');

        await flushPromises();

        expect(wrapper.find('.sw-bulk-edit-save-modal-confirm').exists()).toBeTruthy();

        const footerLeft = wrapper.find('.footer-left');
        await footerLeft.find('button').trigger('click');

        await flushPromises();
        expect(wrapper.vm.$route.path).toBe('/index');
        expect(wrapper.find('.sw-bulk-edit-save-modal-confirm').exists()).toBeFalsy();
    });

    it('should open process and success modal', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        await wrapper.find('.sw-bulk-edit-customer__save-action').trigger('click');

        await flushPromises();

        expect(wrapper.find('.sw-bulk-edit-save-modal-confirm').exists()).toBeTruthy();

        const footerRight = wrapper.find('.footer-right');
        await footerRight.find('button').trigger('click');

        await flushPromises();

        expect(wrapper.vm.$router.options.history.state.back).toBe('/process');
        expect(wrapper.vm.$route.path).toBe('/success');
    });

    it('should open error modal', async () => {
        const spy = jest.spyOn(console, 'error').mockImplementation();
        wrapper = await createWrapper(true);
        await flushPromises();

        await wrapper.setData({
            bulkEditData: {
                ...wrapper.vm.bulkEditData,
                active: {
                    isChanged: true,
                    value: true,
                },
            },
        });

        await wrapper.find('.sw-bulk-edit-customer__save-action').trigger('click');

        await flushPromises();

        expect(wrapper.find('.sw-bulk-edit-save-modal-confirm').exists()).toBeTruthy();

        const footerRight = wrapper.find('.footer-right');
        await footerRight.find('button').trigger('click');

        await flushPromises();

        expect(wrapper.vm.$route.path).toBe('/error');
        expect(spy).toHaveBeenCalledWith(
            new Error('error occurred'),
        );
    });

    it('should show tags and custom fields card', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        const tagsCard = wrapper.find('.sw-bulk-edit-customer-base__tags');
        expect(tagsCard.exists()).toBeTruthy();

        const customFieldsCard = wrapper.find('.sw-bulk-edit-customer-base__custom_fields');
        expect(customFieldsCard.exists()).toBeTruthy();

        wrapper.vm.bulkEditData.customFields.value = {
            field1: 'abc',
        };

        await tagsCard.find('.sw-bulk-edit-change-field__change input').trigger('click');
        await flushPromises();

        const { syncData } = wrapper.vm.onProcessData();
        await flushPromises();

        const changeTagField = syncData[0];
        expect(changeTagField.field).toBe('tags');
        expect(changeTagField.type).toBe('overwrite');

        const changeCustomField = syncData[1];
        expect(changeCustomField.field).toBe('customFields');
        expect(changeCustomField.value).toStrictEqual(wrapper.vm.bulkEditData.customFields.value);
    });

    it('should show account card', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        const accountCard = wrapper.find('.sw-bulk-edit-customer-base__account');
        expect(accountCard.exists()).toBeTruthy();

        await accountCard.findAll('.sw-bulk-edit-change-field__change input').at(0).trigger('click');
        await flushPromises();

        const { syncData } = wrapper.vm.onProcessData();
        await flushPromises();

        const changeAccountField = syncData[0];
        expect(changeAccountField.field).toBe('groupId');
        expect(changeAccountField.type).toBe('overwrite');
    });

    it('should set route meta module when component created', async () => {
        wrapper = await createWrapper();
        await flushPromises();
        wrapper.vm.setRouteMetaModule = jest.fn();

        wrapper.vm.createdComponent();
        expect(wrapper.vm.setRouteMetaModule).toHaveBeenCalled();
        expect(wrapper.vm.$route.meta.$module.color).toBe('#F88962');
        expect(wrapper.vm.$route.meta.$module.icon).toBe('regular-users');

        wrapper.vm.setRouteMetaModule.mockRestore();
    });

    it('should disable processing button', async () => {
        wrapper = await createWrapper();

        await wrapper.setData({
            isLoading: false,
            bulkEditData: {
                accept: {
                    isChanged: false,
                },
                decline: {
                    isChanged: false,
                },
                active: {
                    isChanged: false,
                },
            },
        });
        expect(wrapper.find('.sw-button-process').classes()).toContain('sw-button--disabled');

        await wrapper.setData({
            isLoading: false,
            bulkEditData: {
                accept: {
                    isChanged: true,
                },
                decline: {
                    isChanged: false,
                },
                active: {
                    isChanged: false,
                },
            },
        });
        expect(wrapper.find('.sw-button-process').classes()).not.toContain('sw-button--disabled');
    });
});
