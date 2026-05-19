/**
 * @internal
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';

const createDefaultUser = function () {
    return {
        id: '1',
        attributes: {
            id: '1',
            title: '',
            firstName: 'foo',
            lastName: 'bar',
            email: 'foo@bar.baz',
            localeId: 'one',
            timeZone: '111',
            admin: true,
        },
        relationships: {
            aclRoles: {
                data: [],
                links: {
                    related: 'http://localhost/',
                },
            },
            accessKeys: {
                data: [
                    {
                        id: '12',
                        accessKey: 'accessKey',
                        secretAccessKey: 'secretAccessKey',
                    },
                ],
                links: {
                    related: 'http://localhost/',
                },
            },
        },
    };
};

const responses = global.repositoryFactoryMock.responses;
responses.addResponse({
    method: 'Post',
    url: '/search/language',
    status: 200,
    response: {
        data: [
            {
                id: 'one',
                attributes: {
                    id: 'one',
                    localeId: 'locale1',
                    name: 'languageOne',
                    locale: {
                        id: 'locale1',
                        translated: {
                            name: 'localeOne',
                        },
                    },
                },
                relationships: [],
            },
            {
                id: 'two',
                attributes: {
                    id: 'two',
                    localeId: 'locale2',
                    name: 'languageTwo',
                    locale: {
                        id: 'locale2',
                        translated: {
                            name: 'localeTwo',
                        },
                    },
                },
                relationships: [],
            },
        ],
    },
});

responses.addResponse({
    method: 'Post',
    url: '/search/user-config',
    status: 200,
    response: {
        data: [
            {
                id: 'YourId',
                attributes: {
                    id: 'YourId',
                },
                relationships: [],
            },
        ],
    },
});

Shopware.Service().register('timezoneService', () => {
    return {
        getTimezoneOptions: () => {
            return [
                {
                    id: '111',
                    name: 'tz111',
                },
                {
                    id: '112',
                    name: 'tz112',
                },
            ];
        },
    };
});

async function createWrapper(user) {
    const userResult = user || createDefaultUser();

    responses.addResponse({
        method: 'Post',
        url: '/search/user',
        status: 200,
        response: {
            included: [],
            data: [
                userResult,
            ],
        },
    });

    const wrapper = mount(
        await wrapTestComponent('sw-sso-users-permission-user-detail', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'sw-select-base': await wrapTestComponent('sw-select-base'),
                    'sw-block-field': await wrapTestComponent('sw-block-field'),
                    'sw-base-field': await wrapTestComponent('sw-base-field'),
                    'sw-field-error': await wrapTestComponent('sw-field-error'),
                    'sw-popover': await wrapTestComponent('sw-popover'),
                    'sw-label': await wrapTestComponent('sw-label'),
                    'sw-product-variant-info': await wrapTestComponent('sw-product-variant-info'),
                    'sw-entity-multi-select': await wrapTestComponent('sw-entity-multi-select'),
                    'sw-context-menu': await wrapTestComponent('sw-context-menu'),
                    'sw-context-menu-item': await wrapTestComponent('sw-context-menu-item'),
                    'sw-data-grid': await wrapTestComponent('sw-data-grid'),
                    'sw-data-grid-column-boolean': await wrapTestComponent('sw-data-grid-column-boolean'),
                    'sw-card-view': await wrapTestComponent('sw-card-view'),
                    'sw-user-sso-access-key-create-modal': await wrapTestComponent('sw-user-sso-access-key-create-modal'),
                    'sw-page': await wrapTestComponent('sw-page'),
                    'sw-app-topbar-button': await wrapTestComponent('sw-app-topbar-button'),
                    'sw-app-topbar-sidebar': true,
                    'sw-notification-center': await wrapTestComponent('sw-notification-center'),
                    'sw-notification-center-item': await wrapTestComponent('sw-notification-center-item'),
                    'sw-context-button': await wrapTestComponent('sw-context-button'),
                    'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
                    'sw-select-result': await wrapTestComponent('sw-select-result'),
                    'sw-select-selection-list': await wrapTestComponent('sw-select-selection-list'),
                    'sw-empty-state': true,
                    'sw-search-bar': true,
                    'sw-help-center-v2': true,
                    'router-link': true,
                    'sw-app-actions': true,
                    'sw-error-summary': true,
                    'sw-button-process': true,
                    'sw-data-grid-settings': true,
                    'sw-data-grid-inline-edit': true,
                    'sw-data-grid-skeleton': true,
                    'sw-provide': true,
                    'sw-help-text': true,
                    'sw-ai-copilot-badge': true,
                    'sw-inheritance-switch': true,
                    'sw-loader': true,
                    'sw-highlight-text': true,
                    'sw-upload-listener': true,
                    'sw-media-upload-v2': true,
                },

                provide: {
                    acl: {
                        can: () => {
                            return true;
                        },
                    },
                    integrationService: {
                        generateKey: () => {
                            return Promise.resolve({
                                accessKey: 'accessKey',
                                secretAccessKey: 'secretAccessKey',
                            });
                        },
                    },
                    mediaService: {
                        addListener: () => {},
                        removeListener: () => {},
                        mediaService: () => {},
                        getDefaultFolderId: () => {},
                    },
                    userService: {
                        getUser: () => {
                            return Promise.resolve({
                                id: '2',
                                attributes: {
                                    id: '2',
                                },
                                relationships: {
                                    accessKeys: {
                                        data: [
                                            {
                                                id: '12',
                                                accessKey: 'accessKey',
                                                secretAccessKey: 'secretAccessKey',
                                            },
                                        ],
                                        links: {
                                            related: 'http://localhost/',
                                        },
                                    },
                                },
                            });
                        },
                    },
                },

                mocks: {
                    $route: {
                        params: {
                            id: '1',
                        },
                        meta: {
                            $module: {
                                icon: 'regular-content',
                                description: 'Foo bar',
                            },
                        },
                    },
                },
            },
        },
    );

    await flushPromises();

    return wrapper;
}

describe('module/sw-users-permissions/page/sw-sso-users-permission-user-detail', () => {
    it('should not show invitation banner', async () => {
        const wrapper = await createWrapper();

        const invitationBanner = wrapper.find('.sw-sso-user-invitation-info');
        expect(invitationBanner.exists()).toBeFalsy();
    });

    it('should show invitation banner', async () => {
        const user = createDefaultUser();
        user.attributes.firstName = user.attributes.email;
        user.attributes.lastName = user.attributes.email;

        const wrapper = await createWrapper(user);

        const invitationBanner = wrapper.find('.sw-sso-user-invitation-info');
        expect(invitationBanner.exists()).toBeTruthy();
        expect(invitationBanner.find('.mt-banner__message').text()).toBe(
            'sw-users-permissions.sso.invitationNotYetAccepted',
        );
    });

    it('should not be possible to edit fistName, lastName, email', async () => {
        const wrapper = await createWrapper();

        const firstNameField = wrapper.find('#sw-field--user-firstName');
        expect(firstNameField.exists()).toBeTruthy();
        const lastNameField = wrapper.find('#sw-field--user-lastName');
        expect(lastNameField.exists()).toBeTruthy();
        const emailField = wrapper.find('#sw-field--user-email');
        expect(emailField.exists()).toBeTruthy();

        expect(firstNameField.attributes('disabled')).toBeDefined();
        expect(firstNameField.attributes('disabled')).toBe('');

        expect(lastNameField.attributes('disabled')).toBeDefined();
        expect(lastNameField.attributes('disabled')).toBe('');

        expect(emailField.attributes('disabled')).toBeDefined();
        expect(emailField.attributes('disabled')).toBe('');
    });

    it('should not be possible to edit fistName, lastName, email with given user', async () => {
        const user = createDefaultUser();
        user.attributes.firstName = user.attributes.email;
        user.attributes.lastName = user.attributes.email;

        const wrapper = await createWrapper(user);

        const firstNameField = wrapper.find('#sw-field--user-firstName');
        expect(firstNameField.exists()).toBeTruthy();
        const lastNameField = wrapper.find('#sw-field--user-lastName');
        expect(lastNameField.exists()).toBeTruthy();
        const emailField = wrapper.find('#sw-field--user-email');
        expect(emailField.exists()).toBeTruthy();

        expect(firstNameField.attributes('disabled')).toBeDefined();
        expect(firstNameField.attributes('disabled')).toBe('');

        expect(lastNameField.attributes('disabled')).toBeDefined();
        expect(lastNameField.attributes('disabled')).toBe('');

        expect(emailField.attributes('disabled')).toBeDefined();
        expect(emailField.attributes('disabled')).toBe('');
    });

    it('should disable the roles field', async () => {
        const wrapper = await createWrapper();

        const aclSelect = wrapper.find('.sw-sso-detail-card__roles-and-permission-aclRoles');
        expect(aclSelect.attributes('class')).toContain('is--disabled');
    });

    it('should enable the roles field', async () => {
        const user = createDefaultUser();
        user.attributes.admin = false;

        const wrapper = await createWrapper(user);

        const aclSelect = wrapper.find('.sw-sso-detail-card__roles-and-permission-aclRoles');

        expect(aclSelect.attributes('class')).not.toContain('is--disabled');
    });

    it('should show the create access key modal', async () => {
        const wrapper = await createWrapper();

        let modalAccessKeyField = wrapper.find('.sw-settings-sso-user-create-access-key-modal');
        expect(modalAccessKeyField.exists()).toBeFalsy();

        const createAccessKeyButton = wrapper.find('.sw-sso-detail-card__integrations-create-access-key');
        await createAccessKeyButton.trigger('click');
        await flushPromises();

        modalAccessKeyField = wrapper.find('.sw-settings-sso-user-create-access-key-modal');

        expect(modalAccessKeyField.isVisible()).toBeTruthy();
    });
});
