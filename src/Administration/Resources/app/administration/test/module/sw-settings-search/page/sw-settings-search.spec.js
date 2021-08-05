import { createLocalVue, shallowMount, config } from '@vue/test-utils';
import VueRouter from 'vue-router';
import 'src/module/sw-settings-search/page/sw-settings-search';
import 'src/app/component/base/sw-tabs';
import 'src/app/component/base/sw-tabs-item';
import 'src/app/component/base/sw-button-process';
import 'src/app/component/base/sw-modal';
import 'src/app/component/modal/sw-confirm-modal';

const { Context } = Shopware;
const { EntityCollection } = Shopware.Data;

const mockData = [
    {
        andLogic: false,
        minSearchLength: 4,
        excludedTerms: [],
        languageId: '2fbb5fe2e29a4d70aa5854ce7ce3e20b'
    },
    {
        andLogic: true,
        minSearchLength: 4,
        excludedTerms: [],
        languageId: '2fbb5fe2e29a4d70aa5854ce7ce3e20c'
    }
];

function createWrapper() {
    // delete global $router and $routes mocks
    delete config.mocks.$router;
    delete config.mocks.$route;

    const localVue = createLocalVue();
    localVue.use(VueRouter);
    localVue.directive('tooltip', {});

    const router = new VueRouter({
        routes: [{
            name: 'sw.settings.search.index.general',
            path: '/sw/settings/search/index/general',
            component: Shopware.Component.build('sw-settings-search')
        }, {
            name: 'sw.settings.search.index.liveSearch',
            path: '/sw/settings/search/index/live-search/'
        }]
    });

    return shallowMount(Shopware.Component.build('sw-settings-search'), {
        localVue,
        router,
        provide: {
            repositoryFactory: {
                create: () => ({
                    search: () => {
                        return Promise.resolve(new EntityCollection('', '', Context.api, null, mockData));
                    },
                    save: (productSearchConfigs) => {
                        if (!productSearchConfigs) {
                            // eslint-disable-next-line prefer-promise-reject-errors
                            return Promise.reject({ error: 'Error' });
                        }
                        return Promise.resolve();
                    },
                    create: jest.fn(() => {
                        return {};
                    })
                })
            }
        },

        stubs: {
            'sw-page': {
                template: `
                    <div class="sw-page">
                        <slot name="search-bar"></slot>
                        <slot name="smart-bar-back"></slot>
                        <slot name="smart-bar-header"></slot>
                        <slot name="language-switch"></slot>
                        <slot name="smart-bar-actions"></slot>
                        <slot name="side-content"></slot>
                        <slot name="content"></slot>
                        <slot name="sidebar"></slot>
                        <slot></slot>
                    </div>
                `
            },
            'sw-icon': true,
            'sw-language-switch': true,
            'sw-button': {
                template: '<button @click="$emit(\'click\', $event)"><slot></slot></button>'
            },
            'sw-card-view': {
                template: `
                    <div class="sw-card-view">
                        <slot></slot>
                    </div>
                `
            },
            'sw-tabs': Shopware.Component.build('sw-tabs'),
            'sw-tabs-item': Shopware.Component.build('sw-tabs-item'),
            'sw-button-process': Shopware.Component.build('sw-button-process'),
            'sw-confirm-modal': Shopware.Component.build('sw-confirm-modal'),
            'sw-modal': true,
            'router-link': true,
            'router-view': true
        }
    });
}

describe('module/sw-settings-search/page/sw-settings-search', () => {
    beforeEach(() => {
        global.activeAclRoles = [];
    });

    it('should be a Vue.JS component', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should not able to save product search config without editor privilege', async () => {
        global.activeAclRoles = ['product_search_config.viewer'];

        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const saveButton = wrapper.find('.sw-settings-search__button-save');
        expect(saveButton.attributes().disabled).toBe('disabled');
    });

    it('should able to save product search config if having editor privilege', async () => {
        global.activeAclRoles = ['product_search_config.editor'];

        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const saveButton = wrapper.find('.sw-settings-search__button-save');
        expect(saveButton.attributes().disabled).toBeFalsy();
    });

    it('onSaveSearchSettings: should call to save function when the save button was clicked', async () => {
        global.activeAclRoles = ['product_search_config.editor'];

        const wrapper = createWrapper();

        await wrapper.vm.$nextTick();

        await wrapper.setData({
            productSearchConfigs: {
                andLogic: true,
                minSearchLength: 2
            }
        });

        const onSaveSearchSettingsSpy = jest.spyOn(wrapper.vm, 'onSaveSearchSettings');
        wrapper.vm.productSearchRepository.save = jest.fn(() => Promise.resolve());

        await wrapper.vm.$nextTick();
        await wrapper.get('.sw-settings-search__button-save').trigger('click');

        expect(onSaveSearchSettingsSpy).toBeCalled();
        expect(wrapper.vm.productSearchRepository.save).toHaveBeenCalled();
    });

    it('should be show successful notification when save configuration is succeed', async () => {
        global.activeAclRoles = ['product_search_config.editor'];

        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        wrapper.vm.createNotificationSuccess = jest.fn();
        wrapper.vm.createNotificationError = jest.fn();
        wrapper.vm.getProductSearchConfigs = jest.fn();
        wrapper.vm.productSearchConfigs = {
            andLogic: true,
            minSearchLength: 2
        };

        await wrapper.vm.onSaveSearchSettings();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.getProductSearchConfigs).toHaveBeenCalled();
        expect(wrapper.vm.createNotificationSuccess).toHaveBeenCalledWith({
            message: 'sw-settings-search.notification.saveSuccess'
        });
    });

    it('should be show error notification when save configuration is failed', async () => {
        global.activeAclRoles = ['product_search_config.editor'];

        const wrapper = createWrapper();

        wrapper.vm.createNotificationSuccess = jest.fn();
        wrapper.vm.createNotificationError = jest.fn();
        wrapper.vm.productSearchConfigs = null;

        await wrapper.vm.onSaveSearchSettings();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'sw-settings-search.notification.saveError'
        });
        expect(wrapper.vm.createNotificationSuccess).not.toHaveBeenCalled();
    });

    it('should assign new value when the new language was switch', async () => {
        global.activeAclRoles = ['product_search_config.editor'];

        const wrapper = await createWrapper();

        await wrapper.vm.getProductSearchConfigs();

        expect(wrapper.vm.productSearchConfigs.andLogic).toBe(mockData[0].andLogic);
        expect(wrapper.vm.productSearchConfigs.minSearchLength).toBe(mockData[0].minSearchLength);
        expect(wrapper.vm.productSearchConfigs.excludedTerms.length).toBe(0);
        expect(wrapper.vm.productSearchConfigs.languageId).toBe('2fbb5fe2e29a4d70aa5854ce7ce3e20b');
    });

    it('should show unsaved warning modal', async () => {
        const wrapper = await createWrapper([
            'product_search_config.editor'
        ]);
        const unsavedDataLeaveHandlerSpy = jest.spyOn(wrapper.vm, 'unsavedDataLeaveHandler');

        await wrapper.setData({
            isEditing: true,
            isDisplayingLeavePageWarning: false
        });

        wrapper.vm.$router.push('/sw/settings/search/index/live-search');
        await wrapper.vm.$nextTick();

        const beforeRouteUpdate = wrapper.vm.$options.beforeRouteUpdate[0];
        const nextFun = jest.fn();
        beforeRouteUpdate.call(wrapper.vm, 'toObj', 'fromObj', nextFun);
        expect(unsavedDataLeaveHandlerSpy).toHaveBeenCalledTimes(1);

        // after changing the router with isEditing == true
        // this isDisplayingLeavePageWarning should be changed to true
        // in the method unsavedDataLeaveHandler()
        expect(wrapper.vm.$data.isDisplayingLeavePageWarning).toBe(true);
        await wrapper.vm.$nextTick();

        // if both isEditing and isDisplayingLeavePageWarning the confirm modal should show
        const confirmModal = wrapper.find('.sw_settings_search_leave_modal');
        expect(confirmModal.isVisible()).toBe(true);
    });
});
