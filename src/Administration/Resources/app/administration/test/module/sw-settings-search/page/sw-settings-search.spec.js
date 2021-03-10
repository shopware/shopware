import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-settings-search/page/sw-settings-search';
import 'src/app/component/base/sw-tabs';
import 'src/app/component/base/sw-tabs-item';

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-settings-search'), {
        localVue,
        mocks: {
            $tc: key => key,
            $route: {
                query: {
                    page: 1,
                    limit: 25
                }
            },
            $device: {
                onResize: () => {},
                getSystemKey: () => {}
            }
        },

        provide: {
            repositoryFactory: {
                create: () => ({
                    search: () => {
                        return Promise.resolve();
                    },
                    save: (productSearchConfigs) => {
                        if (!productSearchConfigs) {
                            // eslint-disable-next-line prefer-promise-reject-errors
                            return Promise.reject({ error: 'Error' });
                        }
                        return Promise.resolve();
                    }
                })
            },
            feature: {
                isActive: () => true
            },
            acl: {
                can: (identifier) => {
                    if (!identifier) {
                        return true;
                    }

                    return privileges.includes(identifier);
                }
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
            'router-link': true,
            'router-view': true
        }
    });
}

describe('module/sw-settings-search/page/sw-settings-search', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should not able to save product search config without editor privilege', async () => {
        const wrapper = createWrapper([
            'product_search_config.viewer'
        ]);
        await wrapper.vm.$nextTick();

        const saveButton = wrapper.find('.sw-settings-search__button-save');
        expect(saveButton.attributes().disabled).toBe('disabled');
    });

    it('should able to save product search config if having editor privilege', async () => {
        const wrapper = createWrapper([
            'product_search_config.editor'
        ]);
        await wrapper.vm.$nextTick();

        const saveButton = wrapper.find('.sw-settings-search__button-save');
        expect(saveButton.attributes().disabled).toBeFalsy();
    });

    it('onSaveSearchSettings: should call to save function when the save button was clicked', async () => {
        const wrapper = createWrapper([
            'product_search_config.editor'
        ]);

        await wrapper.vm.$nextTick();

        wrapper.setData({
            productSearchConfigs: {
                andLogic: true,
                minSearchLength: 2
            }
        });

        const onSaveSearchSettingsSpy = jest.spyOn(wrapper.vm, 'onSaveSearchSettings');
        wrapper.vm.productSearchRepository.save = jest.fn();

        await wrapper.vm.$nextTick();
        const saveButton = await wrapper.find('.sw-settings-search__button-save');

        await saveButton.trigger('click');
        expect(onSaveSearchSettingsSpy).toBeCalled();
        expect(wrapper.vm.productSearchRepository.save).toHaveBeenCalled();
    });

    it('should be show successful notification when save configuration is succeed', async () => {
        const wrapper = createWrapper([
            'product_search_config.editor'
        ]);
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
        expect(wrapper.vm.createNotificationError).not.toHaveBeenCalled();
    });

    it('should be show error notification when save configuration is failed', async () => {
        const wrapper = createWrapper([
            'product_search_config.editor'
        ]);

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
});
