import { config, createLocalVue, mount } from '@vue/test-utils';
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
import 'src/module/sw-bulk-edit/page/sw-bulk-edit-product';
import 'src/module/sw-bulk-edit/component/sw-bulk-edit-custom-fields';
import 'src/module/sw-bulk-edit/component/sw-bulk-edit-change-type-field-renderer';
import 'src/module/sw-bulk-edit/component/sw-bulk-edit-form-field-renderer';
import 'src/module/sw-bulk-edit/component/sw-bulk-edit-change-type';
import 'src/module/sw-bulk-edit/component/sw-bulk-edit-save-modal';
import 'src/module/sw-bulk-edit/component/sw-bulk-edit-save-modal-confirm';
import 'src/module/sw-bulk-edit/component/sw-bulk-edit-save-modal-process';
import 'src/module/sw-bulk-edit/component/sw-bulk-edit-save-modal-success';
import 'src/module/sw-bulk-edit/component/sw-bulk-edit-save-modal-error';
import 'src/app/component/base/sw-modal';


const routes = [
    {
        name: 'sw.bulk.edit.product',
        path: 'index'
    },
    {
        name: 'sw.bulk.edit.product.save',
        path: '',
        component: Shopware.Component.build('sw-bulk-edit-save-modal'),
        meta: { $module: {
            title: 'sw-bulk-edit-product.general.mainMenuTitle'
        } },
        redirect: {
            name: 'sw.bulk.edit.product.save.confirm'
        },
        children: [
            {
                name: 'sw.bulk.edit.product.save.confirm',
                path: 'confirm',
                component: Shopware.Component.build('sw-bulk-edit-save-modal-confirm'),
                meta: { $module: {
                    title: 'sw-bulk-edit-product.general.mainMenuTitle'
                } }
            },
            {
                name: 'sw.bulk.edit.product.save.process',
                path: 'process',
                component: Shopware.Component.build('sw-bulk-edit-save-modal-process'),
                meta: { $module: {
                    title: 'sw-bulk-edit-product.general.mainMenuTitle'
                } }
            },
            {
                name: 'sw.bulk.edit.product.save.success',
                path: 'success',
                component: Shopware.Component.build('sw-bulk-edit-save-modal-success'),
                meta: { $module: {
                    title: 'sw-bulk-edit-product.general.mainMenuTitle'
                } }
            },
            {
                name: 'sw.bulk.edit.product.save.error',
                path: 'error',
                component: Shopware.Component.build('sw-bulk-edit-save-modal-error'),
                meta: { $module: {
                    title: 'sw-bulk-edit-product.general.mainMenuTitle'
                } }
            }
        ]
    }
];

const router = new VueRouter({
    routes
});

let bulkEditResponse = {
    success: true
};

function createWrapper() {
    // delete global $router and $routes mocks
    delete config.mocks.$router;
    delete config.mocks.$route;

    const localVue = createLocalVue();
    localVue.use(VueRouter);

    return mount(Shopware.Component.build('sw-bulk-edit-product'), {
        localVue,
        router,
        stubs: {
            'sw-page': Shopware.Component.build('sw-page'),
            'sw-loader': Shopware.Component.build('sw-loader'),
            'sw-button': Shopware.Component.build('sw-button'),
            'sw-bulk-edit-custom-fields': Shopware.Component.build('sw-bulk-edit-custom-fields'),
            'sw-bulk-edit-change-type-field-renderer': Shopware.Component.build('sw-bulk-edit-change-type-field-renderer'),
            'sw-bulk-edit-form-field-renderer': Shopware.Component.build('sw-bulk-edit-form-field-renderer'),
            'sw-bulk-edit-change-type': Shopware.Component.build('sw-bulk-edit-change-type'),
            'sw-form-field-renderer': Shopware.Component.build('sw-form-field-renderer'),
            'sw-empty-state': Shopware.Component.build('sw-empty-state'),
            'sw-button-process': Shopware.Component.build('sw-button-process'),
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
            'sw-bulk-edit-save-modal': Shopware.Component.build('sw-bulk-edit-save-modal'),
            'sw-custom-field-set-renderer': true,
            'sw-text-editor-toolbar': true,
            'sw-app-actions': true,
            'sw-search-bar': true,
            'sw-datepicker': true,
            'sw-text-editor': true,
            'sw-language-switch': true,
            'sw-notification-center': true,
            'sw-icon': true,
            'sw-alert': true,
            'sw-label': true
        },
        props: {
            title: 'Foo bar'
        },
        provide: {
            validationService: {},
            bulkEditApiFactory: {
                getHandler: () => {
                    return {
                        bulkEdit: (selectedIds) => {
                            if (selectedIds.length === 0) {
                                return Promise.reject();
                            }

                            return Promise.resolve(bulkEditResponse);
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

describe('src/module/sw-bulk-edit/page/sw-bulk-edit-product', () => {
    let wrapper;

    beforeEach(() => {
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
        wrapper.vm.$router.push({ path: 'confirm' });
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should open confirm modal', async () => {
        const infoForm = wrapper.find('.sw-bulk-edit-product-base__info');
        const activeField = infoForm.find('.sw-bulk-edit-change-field-active');
        await activeField.find('.sw-bulk-edit-change-field__change input').trigger('click');

        await wrapper.vm.$nextTick();

        await activeField.find('.sw-field--switch__input input').trigger('click');

        expect(wrapper.vm.bulkEditProduct.active.isChanged).toBeTruthy();
        expect(wrapper.vm.bulkEditProduct.active.value).toBeTruthy();

        const dataChanges = wrapper.vm.onProcessData();
        dataChanges.forEach((change) => {
            const changeField = wrapper.vm.bulkEditProduct[change.field];
            expect(changeField.type).toEqual(change.type);
            expect(changeField.value).toEqual(change.value);
        });

        await wrapper.vm.$nextTick();

        await wrapper.find('.sw-bulk-edit-product__save-action').trigger('click');

        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-bulk-edit-save-modal-confirm').exists()).toBeTruthy();
        expect(wrapper.vm.$route.path).toEqual('/confirm');
    });

    it('should close confirm modal', async () => {
        await wrapper.find('.sw-bulk-edit-product__save-action').trigger('click');

        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-bulk-edit-save-modal-confirm').exists()).toBeTruthy();

        const footerLeft = wrapper.find('.footer-left');
        footerLeft.find('button').trigger('click');

        expect(wrapper.vm.$route.path).toEqual('index');
    });

    it('should open process modal', async () => {
        await wrapper.find('.sw-bulk-edit-product__save-action').trigger('click');

        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-bulk-edit-save-modal-confirm').exists()).toBeTruthy();

        const footerRight = wrapper.find('.footer-right');
        footerRight.find('button').trigger('click');

        expect(wrapper.vm.$route.path).toEqual('/process');
    });

    it('should open success modal', async () => {
        await wrapper.find('.sw-bulk-edit-product__save-action').trigger('click');

        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-bulk-edit-save-modal-confirm').exists()).toBeTruthy();

        const footerRight = wrapper.find('.footer-right');
        footerRight.find('button').trigger('click');

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.$route.path).toEqual('/success');
    });

    it('should open fail modal', async () => {
        bulkEditResponse = {
            success: false
        };

        await wrapper.destroy();
        wrapper = await createWrapper();

        await wrapper.find('.sw-bulk-edit-product__save-action').trigger('click');

        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-bulk-edit-save-modal-confirm').exists()).toBeTruthy();

        const footerRight = wrapper.find('.footer-right');
        footerRight.find('button').trigger('click');

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.$route.path).toEqual('/error');
    });

    it('should be show empty state', async () => {
        Shopware.State.commit('shopwareApps/setSelectedIds', []);
        wrapper = createWrapper();

        const emptyState = wrapper.find('.sw-empty-state');
        expect(emptyState.find('.sw-empty-state__title').text()).toBe('sw-bulk-edit.product.messageEmptyTitle');
    });
});
