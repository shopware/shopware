import { config, createLocalVue, mount } from '@vue/test-utils';
import flushPromises from 'flush-promises';
import VueRouter from 'vue-router';
import 'src/app/component/structure/sw-page';
import 'src/app/component/structure/sw-card-view';
import 'src/app/component/utils/sw-loader';
import 'src/app/component/base/sw-container';
import 'src/app/component/base/sw-button';
import 'src/app/component/base/sw-empty-state';
import 'src/app/component/base/sw-button-process';
import 'src/app/component/base/sw-card';
import 'src/app/component/form/sw-field';
import 'src/app/component/form/sw-number-field';
import 'src/app/component/form/sw-switch-field';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/sw-text-editor';
import 'src/app/component/form/sw-textarea-field';
import 'src/app/component/form/sw-custom-field-set-renderer';
import 'src/app/component/form/sw-form-field-renderer';
import 'src/app/component/form/sw-checkbox-field';
import 'src/app/component/form/select/base/sw-single-select';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/field-base/sw-field-error';
import 'src/app/component/form/select/entity/sw-entity-single-select';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/module/sw-bulk-edit/page/sw-bulk-edit-order';
import 'src/module/sw-bulk-edit/component/sw-bulk-edit-custom-fields';
import 'src/module/sw-bulk-edit/component/sw-bulk-edit-change-type-field-renderer';
import 'src/module/sw-bulk-edit/component/sw-bulk-edit-form-field-renderer';
import 'src/module/sw-bulk-edit/component/sw-bulk-edit-change-type';
import 'src/module/sw-bulk-edit/component/sw-bulk-edit-order/sw-bulk-edit-order-documents';
import 'src/app/component/form/sw-select-field';
import 'src/module/sw-bulk-edit/component/sw-bulk-edit-save-modal';
import 'src/module/sw-bulk-edit/component/sw-bulk-edit-save-modal-confirm';
import 'src/module/sw-bulk-edit/component/sw-bulk-edit-save-modal-process';
import 'src/module/sw-bulk-edit/component/sw-bulk-edit-save-modal-success';
import 'src/module/sw-bulk-edit/component/sw-bulk-edit-save-modal-error';
import 'src/app/component/base/sw-modal';

const routes = [
    {
        name: 'sw.bulk.edit.order',
        path: 'index'
    },
    {
        name: 'sw.bulk.edit.order.save',
        path: '',
        component: Shopware.Component.build('sw-bulk-edit-save-modal'),
        meta: { $module: {
            title: 'sw-bulk-edit-order.general.mainMenuTitle'
        } },
        redirect: {
            name: 'sw.bulk.edit.order.save.confirm'
        },
        children: [
            {
                name: 'sw.bulk.edit.order.save.confirm',
                path: 'confirm',
                component: Shopware.Component.build('sw-bulk-edit-save-modal-confirm'),
                meta: { $module: {
                    title: 'sw-bulk-edit-order.general.mainMenuTitle'
                } }
            },
            {
                name: 'sw.bulk.edit.order.save.process',
                path: 'process',
                component: Shopware.Component.build('sw-bulk-edit-save-modal-process'),
                meta: { $module: {
                    title: 'sw-bulk-edit-order.general.mainMenuTitle'
                } }
            },
            {
                name: 'sw.bulk.edit.order.save.success',
                path: 'success',
                component: Shopware.Component.build('sw-bulk-edit-save-modal-success'),
                meta: { $module: {
                    title: 'sw-bulk-edit-order.general.mainMenuTitle'
                } }
            },
            {
                name: 'sw.bulk.edit.order.save.error',
                path: 'error',
                component: Shopware.Component.build('sw-bulk-edit-save-modal-error'),
                meta: { $module: {
                    title: 'sw-bulk-edit-order.general.mainMenuTitle'
                } }
            }
        ]
    }
];

const router = new VueRouter({
    routes
});

function createWrapper(isResponseError = false) {
    // delete global $router and $routes mocks
    delete config.mocks.$router;
    delete config.mocks.$route;

    const localVue = createLocalVue();
    localVue.use(VueRouter);

    return mount(Shopware.Component.build('sw-bulk-edit-order'), {
        localVue,
        router,
        stubs: {
            'sw-page': Shopware.Component.build('sw-page'),
            'sw-loader': Shopware.Component.build('sw-loader'),
            'sw-button': Shopware.Component.build('sw-button'),
            'sw-select-field': Shopware.Component.build('sw-select-field'),
            'sw-bulk-edit-custom-fields': Shopware.Component.build('sw-bulk-edit-custom-fields'),
            'sw-bulk-edit-change-type-field-renderer': Shopware.Component.build('sw-bulk-edit-change-type-field-renderer'),
            'sw-bulk-edit-form-field-renderer': Shopware.Component.build('sw-bulk-edit-form-field-renderer'),
            'sw-bulk-edit-change-type': Shopware.Component.build('sw-bulk-edit-change-type'),
            'sw-form-field-renderer': Shopware.Component.build('sw-form-field-renderer'),
            'sw-empty-state': Shopware.Component.build('sw-empty-state'),
            'sw-button-process': Shopware.Component.build('sw-button-process'),
            'sw-bulk-edit-order-documents': Shopware.Component.build('sw-bulk-edit-order-documents'),
            'sw-card': Shopware.Component.build('sw-card'),
            'sw-field': Shopware.Component.build('sw-field'),
            'sw-modal': Shopware.Component.build('sw-modal'),
            'sw-select-base': Shopware.Component.build('sw-select-base'),
            'sw-single-select': Shopware.Component.build('sw-single-select'),
            'sw-number-field': Shopware.Component.build('sw-number-field'),
            'sw-switch-field': Shopware.Component.build('sw-switch-field'),
            'sw-text-field': Shopware.Component.build('sw-text-field'),
            'sw-textarea-field': Shopware.Component.build('sw-textarea-field'),
            'sw-checkbox-field': Shopware.Component.build('sw-checkbox-field'),
            'sw-contextual-field': Shopware.Component.build('sw-contextual-field'),
            'sw-block-field': Shopware.Component.build('sw-block-field'),
            'sw-base-field': Shopware.Component.build('sw-base-field'),
            'sw-container': Shopware.Component.build('sw-container'),
            'sw-field-error': Shopware.Component.build('sw-field-error'),
            'sw-entity-single-select': Shopware.Component.build('sw-entity-single-select'),
            'sw-card-view': Shopware.Component.build('sw-card-view'),
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
            'sw-label': true
        },
        props: {
            title: 'Foo bar'
        },
        provide: {
            validationService: {},
            repositoryFactory: {
                create: () => {
                    return {
                        create: () => {
                            return {
                                id: '1a2b3c',
                                name: 'Test order'
                            };
                        },
                        search: () => Promise.resolve([
                            {
                                id: 1,
                                name: 'Invoice'
                            },
                            {
                                id: 2,
                                name: 'Credit note'
                            }
                        ]),
                        get: () => Promise.resolve({
                            id: 1,
                            name: 'Order'
                        }),
                        searchIds: () => Promise.resolve([
                            {
                                data: [1],
                                total: 1
                            }
                        ])
                    };
                }
            },
            bulkEditApiFactory: {
                getHandler: () => {
                    return {
                        bulkEdit: (selectedIds) => {
                            if (isResponseError) {
                                return Promise.reject(new Error('error occured'));
                            }

                            if (selectedIds.length === 0) {
                                return Promise.reject();
                            }

                            return Promise.resolve();
                        },

                        bulkEditStatus: (selectedIds) => {
                            if (isResponseError) {
                                return Promise.reject(new Error('error occured'));
                            }

                            if (selectedIds.length === 0) {
                                return Promise.reject();
                            }

                            return Promise.resolve();
                        }
                    };
                }
            },
            shortcutService: {
                startEventListener: () => {},
                stopEventListener: () => {}
            }
        }
    });
}

describe('src/module/sw-bulk-edit/page/sw-bulk-edit-order', () => {
    let wrapper;

    beforeEach(() => {
        jest.spyOn(console, 'log').mockImplementation(() => {});
        const mockResponses = global.repositoryFactoryMock.responses;
        mockResponses.addResponse({
            method: 'post',
            url: '/search/document-type',
            status: 200,
            response: {
                data: [
                    {
                        id: Shopware.Utils.createId(),
                        attributes: {
                            id: Shopware.Utils.createId()
                        }
                    }
                ]
            }
        });

        Shopware.State.commit('shopwareApps/setSelectedIds', [Shopware.Utils.createId()]);
    });

    afterEach(() => {
        wrapper.destroy();
        wrapper.vm.$router.push({ path: 'confirm' });
    });

    beforeAll(() => {
        jest.spyOn(global.console, 'error').mockImplementation(() => {});
    });

    it('should show all form fields', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-bulk-edit-change-field-renderer').exists()).toBeTruthy();
    });

    // TODO: NEXT-6061 - unskip when we allow to send status mails and documents
    it.skip('should disable status mails and documents by default', async () => {
        wrapper = await createWrapper();

        await flushPromises();

        expect(wrapper.find('.sw-bulk-edit-change-field-statusMails .sw-field__checkbox input').attributes().disabled).toBeTruthy();
        expect(wrapper.find('.sw-bulk-edit-change-field-documents .sw-field__checkbox input').attributes().disabled).toBeTruthy();
    });

    // TODO: NEXT-6061 - unskip when we allow to send status mails and documents
    it.skip('should enable status mails when one of the status fields has changed', async () => {
        wrapper = createWrapper();

        await flushPromises();

        wrapper.setData({
            bulkEditData: {
                ...wrapper.vm.bulkEditData,
                orderTransactions: {
                    isChanged: true,
                    value: '1'
                }
            }
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-bulk-edit-change-field-statusMails .sw-field__checkbox input').attributes().disabled).toBeFalsy();
    });

    // TODO: NEXT-6061 - unskip when we allow to send status mails and documents
    it.skip('should enable documents when status mails is enabled', async () => {
        wrapper = createWrapper();

        await flushPromises();

        wrapper.setData({
            bulkEditData: {
                ...wrapper.vm.bulkEditData,
                orderTransactions: {
                    isChanged: true,
                    value: '1'
                }
            }
        });

        await wrapper.vm.$nextTick();

        wrapper.find('.sw-bulk-edit-change-field-statusMails .sw-field__checkbox input').trigger('click');

        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-bulk-edit-change-field-documents .sw-field__checkbox input').attributes().disabled).toBeFalsy();
    });

    it('should be show empty state', async () => {
        wrapper = createWrapper();

        Shopware.State.commit('shopwareApps/setSelectedIds', []);
        await wrapper.setData({
            isLoading: false
        });

        const emptyState = wrapper.find('.sw-empty-state');
        expect(emptyState.find('.sw-empty-state__title').text()).toBe('sw-bulk-edit.order.messageEmptyTitle');
    });

    it('should open confirm modal', async () => {
        wrapper = createWrapper();
        await flushPromises();

        await wrapper.find('.sw-bulk-edit-order__save-action').trigger('click');

        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-bulk-edit-save-modal-confirm').exists()).toBeTruthy();
        expect(wrapper.vm.$route.path).toEqual('/confirm');
    });

    it('should close confirm modal', async () => {
        wrapper = createWrapper();
        await flushPromises();

        await wrapper.find('.sw-bulk-edit-order__save-action').trigger('click');

        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-bulk-edit-save-modal-confirm').exists()).toBeTruthy();

        const footerLeft = wrapper.find('.footer-left');
        footerLeft.find('button').trigger('click');

        await wrapper.vm.$nextTick();
        expect(wrapper.vm.$route.path).toEqual('index');
        expect(wrapper.find('.sw-bulk-edit-save-modal-confirm').exists()).toBeFalsy();
    });

    it('should open process modal', async () => {
        wrapper = createWrapper();
        await flushPromises();

        await wrapper.find('.sw-bulk-edit-order__save-action').trigger('click');

        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-bulk-edit-save-modal-confirm').exists()).toBeTruthy();

        const footerRight = wrapper.find('.footer-right');
        footerRight.find('button').trigger('click');

        expect(wrapper.vm.$route.path).toEqual('/process');
    });

    it('should open success modal', async () => {
        wrapper = createWrapper();
        await flushPromises();

        await wrapper.find('.sw-bulk-edit-order__save-action').trigger('click');

        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-bulk-edit-save-modal-confirm').exists()).toBeTruthy();

        const footerRight = wrapper.find('.footer-right');
        footerRight.find('button').trigger('click');

        await flushPromises();

        expect(wrapper.vm.$route.path).toEqual('/success');
    });

    it('should open error modal', async () => {
        wrapper = createWrapper(true);
        await flushPromises();

        wrapper.setData({
            bulkEditData: {
                ...wrapper.vm.bulkEditData,
                orderTransactions: {
                    isChanged: true,
                    value: '1'
                }
            }
        });

        await wrapper.find('.sw-bulk-edit-order__save-action').trigger('click');

        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-bulk-edit-save-modal-confirm').exists()).toBeTruthy();

        const footerRight = wrapper.find('.footer-right');
        footerRight.find('button').trigger('click');

        await flushPromises();

        expect(wrapper.vm.$route.path).toEqual('/error');
    });

    it('should show tags and custom fields card', async () => {
        wrapper = createWrapper();
        await flushPromises();

        const tagsCard = wrapper.find('.sw-bulk-edit-order-base__tags');
        expect(tagsCard).toBeTruthy();

        const customFieldsCard = wrapper.find('.sw-card sw-bulk-edit-order-base__custom_fields');
        expect(customFieldsCard).toBeTruthy();

        wrapper.vm.bulkEditData.customFields.value = {
            field1: 'abc'
        };

        await tagsCard.find('.sw-bulk-edit-change-field__change input').trigger('click');
        await wrapper.vm.$nextTick();

        const { syncData } = wrapper.vm.onProcessData();
        await wrapper.vm.$nextTick();

        const changeTagField = syncData[0];
        expect(changeTagField.field).toBe('tags');
        expect(changeTagField.type).toBe('overwrite');

        const changeCustomField = syncData[1];
        expect(changeCustomField.field).toBe('customFields');
        expect(changeCustomField.value).toBe(wrapper.vm.bulkEditData.customFields.value);
    });
});
