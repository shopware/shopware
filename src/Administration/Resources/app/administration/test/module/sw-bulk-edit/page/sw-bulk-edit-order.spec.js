import { createLocalVue, shallowMount } from '@vue/test-utils';
import flushPromises from 'flush-promises';
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

function createWrapper() {
    const localVue = createLocalVue();

    return shallowMount(Shopware.Component.build('sw-bulk-edit-order'), {
        localVue,
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
            'sw-help-text': true
        },
        props: {
            title: 'Foo bar'
        },
        mocks: {
            $route: {
                meta: {
                    $module: {
                        title: 'sw-bulk-edit-order.general.mainMenuTitle'
                    }
                }
            }
        },
        provide: {
            validationService: {},
            repositoryFactory: {
                create: () => {
                    return {
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
                            if (selectedIds.length === 0) {
                                return Promise.reject();
                            }

                            return Promise.resolve();
                        }
                    };
                }
            }
        }
    });
}

describe('src/module/sw-bulk-edit/page/sw-bulk-edit-order', () => {
    let wrapper;

    beforeEach(() => {
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
        wrapper = createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should show all form fields', async () => {
        wrapper = createWrapper();

        expect(wrapper.find('.sw-bulk-edit-change-field-renderer').exists()).toBeTruthy();
    });

    it('should disable status mails and documents by default', async () => {
        wrapper = await createWrapper();

        await flushPromises();

        expect(wrapper.find('.sw-bulk-edit-change-field-statusMails .sw-field__checkbox input').attributes().disabled).toBeTruthy();
        expect(wrapper.find('.sw-bulk-edit-change-field-documents .sw-field__checkbox input').attributes().disabled).toBeTruthy();
    });

    it('should enable status mails when one of the status fields has changed', async () => {
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

    it('should enable documents when status mails is enabled', async () => {
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
        Shopware.State.commit('shopwareApps/setSelectedIds', []);
        wrapper = createWrapper();

        const emptyState = wrapper.find('.sw-empty-state');
        expect(emptyState.find('.sw-empty-state__title').text()).toBe('sw-bulk-edit.order.messageEmptyTitle');
    });
});
