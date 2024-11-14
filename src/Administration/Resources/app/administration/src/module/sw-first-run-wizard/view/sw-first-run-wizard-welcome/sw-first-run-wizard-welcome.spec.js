import { mount } from '@vue/test-utils';
import 'src/module/sw-extension/mixin/sw-extension-error.mixin';

const setLocaleWithIdMock = jest.fn(() => Promise.resolve({}));

Shopware.Service().register('localeHelper', () => {
    return {
        setLocaleWithId: setLocaleWithIdMock,
    };
});

const languagePlugins = {
    items: [
        {
            extensions: [],
            name: 'SwagLanguagePack',
            label: 'Shopware Language Pack',
            // eslint-disable-next-line max-len
            shortDescription:
                'With all languages in one extension, switching languages in your online shop has never been easier! Simply choose the languages for your admin and storefront for you and your customers.',
            iconPath: 'https://sbp-plugin-images.s3.eu-west-1.amazonaws.com/php6TDNDF',
            version: null,
            description: null,
            changelog: null,
            releaseDate: null,
            installed: false,
            active: false,
            language: null,
            region: null,
            category: null,
            manufacturer: 'shopware AG',
            position: null,
            isCategoryLead: false,
        },
    ],
    total: 1,
};

const userProfile = {
    data: {
        id: 'c0cf3e77ad0e4ed8b855d3eb820dbfb4',
        type: 'user',
        relationships: [],
        attributes: {
            id: 'c0cf3e77ad0e4ed8b855d3eb820dbfb4',
            localeId: 'c2e20247ec8e42c0b224a03d458eb8e0',
        },
    },
};

const searchUser = {
    data: [
        {
            id: 'c0cf3e77ad0e4ed8b855d3eb820dbfb4',
            type: 'user',
            relationships: [],
            attributes: {
                id: 'c0cf3e77ad0e4ed8b855d3eb820dbfb4',
                localeId: 'c2e20247ec8e42c0b224a03d458eb8e0',
            },
        },
    ],
};

const searchLanguage = [
    {
        id: '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
        type: 'language',
        locale: {
            id: 'c2e20247ec8e42c0b224a03d458eb8e0',
            translated: {
                name: 'English (US)',
                territory: 'United States',
            },
        },
    },
    {
        id: 'ba44d1a797b8474b9497b59837c63efb',
        type: 'language',
        locale: {
            id: '4aed63b2afcd44049ba0cd898769cdbb',
            translated: {
                name: 'German',
                territory: 'Germany',
            },
        },
    },
];

/**
 * @package checkout
 */
describe('src/module/sw-first-run-wizard/view/sw-first-run-wizard-welcome', () => {
    async function createWrapper() {
        return mount(
            await wrapTestComponent('sw-first-run-wizard-welcome', {
                sync: true,
            }),
            {
                global: {
                    stubs: {
                        'sw-container': await wrapTestComponent('sw-container'),
                        'sw-plugin-card': await wrapTestComponent('sw-plugin-card'),
                        'sw-button-process': await wrapTestComponent('sw-button-process'),
                        'sw-button': await wrapTestComponent('sw-button'),
                        'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated'),
                        'sw-modal': await wrapTestComponent('sw-modal'),
                        'sw-select-field': await wrapTestComponent('sw-select-field', { sync: true }),
                        'sw-select-field-deprecated': await wrapTestComponent('sw-select-field-deprecated', { sync: true }),
                        'sw-block-field': await wrapTestComponent('sw-block-field'),
                        'sw-base-field': await wrapTestComponent('sw-base-field'),
                        'sw-field-error': await wrapTestComponent('sw-field-error'),
                        'sw-password-field': await wrapTestComponent('sw-password-field'),
                        'sw-password-field-deprecated': await wrapTestComponent('sw-password-field-deprecated'),
                        'sw-text-field': await wrapTestComponent('sw-text-field'),
                        'sw-text-field-deprecated': await wrapTestComponent('sw-text-field-deprecated', { sync: true }),
                        'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                        'sw-icon': true,
                        'sw-loader': true,
                        'sw-extension-icon': await wrapTestComponent('sw-extension-icon'),
                        'router-link': true,
                        'sw-inheritance-switch': true,
                        'sw-ai-copilot-badge': true,
                        'sw-help-text': true,
                        'sw-field-copyable': true,
                    },
                    provide: {
                        languagePluginService: {
                            getPlugins: () => Promise.resolve(languagePlugins),
                        },
                        userService: {
                            getUser: () => Promise.resolve(userProfile),
                        },
                        loginService: {
                            verifyUserToken: () => Promise.resolve(),
                        },
                        cacheApiService: {
                            clear: () => Promise.resolve(),
                        },
                        extensionHelperService: {
                            downloadAndActivateExtension: (extension) => Promise.resolve(extension),
                        },
                        shortcutService: {
                            startEventListener: () => {},
                            stopEventListener: () => {},
                        },
                        validationService: {
                            validate: () => true,
                        },
                        repositoryFactory: {
                            create: (entity) => {
                                switch (entity) {
                                    case 'language':
                                        return {
                                            search: () => Promise.resolve(searchLanguage),
                                        };
                                    case 'user':
                                        return {
                                            search: () => Promise.resolve(searchUser),
                                            get: () => Promise.resolve(searchUser),
                                            save: () => Promise.resolve(),
                                        };
                                    default:
                                        throw new Error(`No repositoryFactory registered for entity "${entity}"`);
                                }
                            },
                        },
                        shopwareExtensionService: {
                            updateExtensionData: () => Promise.resolve(),
                        },
                    },
                    mixins: [
                        Shopware.Mixin.getByName('notification'),
                    ],
                },
            },
        );
    }

    beforeAll(() => {
        if (Shopware.State.get('context')) {
            Shopware.State.unregisterModule('context');
        }

        Shopware.State.registerModule('context', {
            namespaced: true,
            state: {
                app: {
                    config: {
                        settings: {
                            disableExtensionManagement: false,
                        },
                    },
                },
                api: {
                    assetPath: 'http://localhost:8000/bundles/administration/',
                    authToken: {
                        token: 'testToken',
                    },
                },
            },
        });
    });

    it('should install the SwagLanguagePack plugin and show the language switch modal', async () => {
        const wrapper = await createWrapper();

        await flushPromises();

        expect(wrapper.findAll('.sw-plugin-card')).toHaveLength(1);

        const button = wrapper.find('.button-plugin-install');
        await button.trigger('click');
        await flushPromises();

        const modal = await wrapper.getComponent('.sw-first-run-wizard-confirmLanguageSwitch-modal');
        expect(modal.isVisible()).toBe(true);

        const languageSelect = await modal.find('.sw-profile__language');
        await languageSelect.findAll('option').at(1).setSelected();

        const selectedLanguage = languageSelect.find('option:checked').element.value;
        await modal.find('input[type="password"]').setValue('p4ssw0rd');
        await modal.find('.sw-button--primary').trigger('click');

        expect(setLocaleWithIdMock).toHaveBeenCalledWith(selectedLanguage);
    });
});
