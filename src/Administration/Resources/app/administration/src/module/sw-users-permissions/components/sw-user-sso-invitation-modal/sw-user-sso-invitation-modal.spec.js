/**
 * @internal
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';

const responses = global.repositoryFactoryMock.responses;

responses.addResponse({
    method: 'Post',
    url: '/search/language',
    status: 200,
    response: {
        data: [
            {
                id: 'idOne',
                localeId: 'localeIdOne',
                name: 'languageOne',
                attributes: {
                    id: 'idOne',
                },
                relationships: [],
            },
            {
                id: 'idTwo',
                localeId: 'localeIdTwo',
                name: 'languageTwo',
                attributes: {
                    id: 'idTwo',
                },
                relationships: [],
            },
        ],
    },
});

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-user-sso-invitation-modal', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'sw-modal': await wrapTestComponent('sw-modal', {
                        sync: true,
                    }),
                    'sw-single-select': await wrapTestComponent('sw-single-select', {
                        sync: true,
                    }),
                    'sw-select-base': await wrapTestComponent('sw-select-base'),
                    'sw-block-field': await wrapTestComponent('sw-block-field'),
                    'sw-base-field': await wrapTestComponent('sw-base-field'),
                    'sw-field-error': await wrapTestComponent('sw-field-error'),
                    'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
                    'sw-popover': await wrapTestComponent('sw-popover'),
                    'sw-popover-deprecated': await wrapTestComponent('sw-popover-deprecated', { sync: true }),
                    'sw-select-result': await wrapTestComponent('sw-select-result'),
                    'sw-highlight-text': await wrapTestComponent('sw-highlight-text'),
                    'sw-inheritance-switch': true,
                    'sw-ai-copilot-badge': true,
                    'sw-help-text': true,
                    'sw-loader': true,
                },

                provide: {
                    shortcutService: {
                        startEventListener: () => {},
                        stopEventListener: () => {},
                    },
                    invitationService: {
                        inviteUser: () => {},
                    },
                },
            },
        },
    );
}

describe('module/sw-users-permissions/components/sw-user-sso-invitation-modal/sw-user-sso-invitation-modal', () => {
    it('should throw "modal-close" event', async () => {
        const wrapper = await createWrapper();

        await wrapper.find('.sw-users-permissions-sso-modal-close-button').trigger('click');

        expect(wrapper.emitted('modal-close')).toBeTruthy();
        expect(wrapper.emitted('user-invited')).toBeFalsy();
        expect(wrapper.emitted('invitation-failed')).toBeFalsy();
    });

    it('should show errors', async () => {
        const wrapper = await createWrapper();

        await wrapper.find('.sw-users-permissions-sso-modal-save-button').trigger('click');

        const emailField = wrapper.find('.sw-users-permissions-sso-modal-field-email');
        expect(emailField.exists()).toBeTruthy();
        expect(emailField.attributes('class')).toContain('has--error');

        const languageField = wrapper.find('.sw-users-permissions-sso-modal-field-email');
        expect(languageField.exists()).toBeTruthy();
        expect(languageField.attributes('class')).toContain('has--error');
    });

    it('should throw "invitation-failed" event', async () => {
        Shopware.Service().register('ssoInvitationService', () => {
            return {
                inviteUser: () => {
                    return Promise.reject();
                },
            };
        });

        const wrapper = await createWrapper();
        await flushPromises();

        const emailField = wrapper.find('.sw-users-permissions-sso-modal-field-email');
        expect(emailField.exists()).toBeTruthy();

        const languageField = wrapper.find('.sw-users-permissions-sso-modal-field-language');
        expect(languageField.exists()).toBeTruthy();

        await emailField.find('input').setValue('test@example.com');

        await languageField.find('.sw-single-select__selection').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-select-option--0').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-users-permissions-sso-modal-save-button').trigger('click');
        await flushPromises();

        expect(wrapper.emitted('user-invited')).toBeFalsy();
        expect(wrapper.emitted('modal-close')).toBeFalsy();
        expect(wrapper.emitted('invitation-failed')).toBeTruthy();
    });

    it('should throw "user-invited" event', async () => {
        Shopware.Application.getContainer('service').ssoInvitationService = {
            inviteUser: () => {
                return Promise.resolve();
            },
        };

        const wrapper = await createWrapper();
        await flushPromises();

        const emailField = wrapper.find('.sw-users-permissions-sso-modal-field-email');
        expect(emailField.exists()).toBeTruthy();

        const languageField = wrapper.find('.sw-users-permissions-sso-modal-field-language');
        expect(languageField.exists()).toBeTruthy();

        await emailField.find('input').setValue('test@example.com');

        await languageField.find('.sw-single-select__selection').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-select-option--1').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-users-permissions-sso-modal-save-button').trigger('click');
        await flushPromises();

        expect(wrapper.emitted('user-invited')).toBeTruthy();
        expect(wrapper.emitted('modal-close')).toBeTruthy();
        expect(wrapper.emitted('invitation-failed')).toBeFalsy();
    });
});
