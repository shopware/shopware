import { mount } from '@vue/test-utils';

import settingCustomerGroupDetailCreateOverride from 'src/module/sw-settings-customer-group/page/sw-settings-customer-group-create';

Shopware.Component.override('sw-settings-customer-group-detail', settingCustomerGroupDetailCreateOverride);

/**
 * @package services-settings
 */
async function createWrapper() {
    return mount(await wrapTestComponent('sw-settings-customer-group-detail', {
        sync: true,
    }), {
        global: {
            mocks: {
                $route: {
                    query: '',
                    meta: { $module: { icon: 'default-symbol-customers' } },
                },
                $router: {
                    push: () => {},
                    currentRoute: {
                        value: {
                            matched: 'sw.settings.customer.group.create',
                        },
                    },
                },
            },

            stubs: {
                'sw-popover': await wrapTestComponent('sw-popover'),
                'sw-page': await wrapTestComponent('sw-page'),
                'sw-card': await wrapTestComponent('sw-card'),
                'sw-card-deprecated': await wrapTestComponent('sw-card-deprecated', { sync: true }),
                'sw-card-view': await wrapTestComponent('sw-card-view'),
                'sw-boolean-radio-group': await wrapTestComponent('sw-boolean-radio-group'),
                'sw-container': await wrapTestComponent('sw-container'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-text-field': await wrapTestComponent('sw-text-field'),
                'sw-text-field-deprecated': await wrapTestComponent('sw-text-field-deprecated', { sync: true }),
                'sw-textarea-field': await wrapTestComponent('sw-textarea-field'),
                'sw-select-base': await wrapTestComponent('sw-select-base'),
                'sw-select-selection-list': await wrapTestComponent('sw-select-selection-list'),
                'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
                'sw-select-result': await wrapTestComponent('sw-select-result'),
                'sw-button': await wrapTestComponent('sw-button'),
                'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated'),
                'sw-button-process': await wrapTestComponent('sw-button-process'),
                'sw-language-info': await wrapTestComponent('sw-language-info'),
                'sw-switch-field': await wrapTestComponent('sw-switch-field'),
                'sw-entity-multi-select': await wrapTestComponent('sw-entity-multi-select'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-label': await wrapTestComponent('sw-label'),
                'sw-loader': await wrapTestComponent('sw-loader'),
                'sw-custom-field-set-renderer': await wrapTestComponent('sw-custom-field-set-renderer'),
                'sw-extension-component-section': await wrapTestComponent('sw-extension-component-section'),
                'sw-language-switch': await wrapTestComponent('sw-language-switch'),
                'sw-notification-center': await wrapTestComponent('sw-notification-center'),
                'sw-help-center-v2': await wrapTestComponent('sw-help-center-v2'),
                'sw-context-button': await wrapTestComponent('sw-context-button'),
                'sw-app-actions': await wrapTestComponent('sw-app-actions'),
                'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                'sw-error-summary': await wrapTestComponent('sw-error-summary'),
                'sw-field-error': await wrapTestComponent('sw-field-error'),
                'sw-entity-single-select': await wrapTestComponent('sw-entity-single-select'),
                'sw-radio-field': await wrapTestComponent('sw-radio-field'),
                'sw-text-editor': true,
                'sw-search-bar': true,
                'sw-highlight-text': true,
                'sw-skeleton': true,
                'sw-icon': true,
            },

            provide: {
                repositoryFactory: {
                    create: () => ({
                        create: () => {
                            return {
                                id: 'aNiceId',
                                name: '',
                                displayGross: true,
                                isNew: () => true,
                            };
                        },

                        save: () => {
                            return Promise.resolve();
                        },

                        get: () => {
                            return Promise.resolve();
                        },
                    }),
                },
                acl: {
                    can: () => {
                        return true;
                    },
                },
            },
        },
    });
}

describe('src/module/sw-settings-customer-group/page/sw-settings-customer-group-create', () => {
    it('should be able to save the customer group with name', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.$router.push = jest.fn();

        const customerGroupNameInput = wrapper.find('input[name="sw-field--customerGroup-name"]');
        await customerGroupNameInput.setValue('New net price customer group');
        await customerGroupNameInput.trigger('change');

        const saveButton = wrapper.find('.sw-settings-customer-group-detail__save');
        await saveButton.trigger('click');
        await flushPromises();

        expect(wrapper.vm.$router.push).toHaveBeenCalledWith({
            name: 'sw.settings.customer.group.detail',
            params: { id: 'aNiceId' },
        });
    });

    it('should not be able to save with repository save error', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.$router.push = jest.fn();
        wrapper.vm.createNotificationError = jest.fn();
        // eslint-disable-next-line prefer-promise-reject-errors
        wrapper.vm.customerGroupRepository.save = jest.fn(() => Promise.reject({
            response: {
                data: {
                    errors: [
                        {
                            code: '0',
                            detail: 'This is an error',
                        },
                    ],
                },
            },
        }));

        const customerGroupNameInput = wrapper.find('input[name="sw-field--customerGroup-name"]');
        await customerGroupNameInput.setValue('New net price customer group');
        await customerGroupNameInput.trigger('change');
        await flushPromises();

        const saveButton = wrapper.find('.sw-settings-customer-group-detail__save');
        await saveButton.trigger('click');
        await flushPromises();

        expect(wrapper.vm.$router.push).not.toHaveBeenCalled();
        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'sw-settings-customer-group.detail.notificationErrorMessage',
        });
    });

    it('should not be able to save the customer group without registration title', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const customerGroupNameInput = wrapper.find('input[name="sw-field--customerGroup-name"]');
        await customerGroupNameInput.setValue('Another customer group');
        await customerGroupNameInput.trigger('change');
        await flushPromises();

        const registrationActiveSwitch = wrapper.find('input[name="sw-field--customerGroup-registrationActive"]');
        await registrationActiveSwitch.setValue(true);
        await flushPromises();

        const titleInput = wrapper.find('input[name="sw-field--customerGroup-registrationTitle"]');
        expect(titleInput.exists()).toBeTruthy();

        const saveButton = wrapper.find('.sw-settings-customer-group-detail__save');
        await saveButton.trigger('click');
        await flushPromises();

        const titleInputWrapper = wrapper.find('div[label="sw-settings-customer-group.registration.title"]');
        expect(titleInputWrapper.classes()).toContain('has--error');
    });
});
