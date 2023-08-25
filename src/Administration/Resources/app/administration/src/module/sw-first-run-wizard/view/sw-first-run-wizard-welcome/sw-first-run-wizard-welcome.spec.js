import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-extension/mixin/sw-extension-error.mixin';
import swFirstRunWizardWelcome from 'src/module/sw-first-run-wizard/view/sw-first-run-wizard-welcome';
import swPluginCard from 'src/module/sw-first-run-wizard/component/sw-plugin-card';
import swExtensionIcon from 'src/app/asyncComponent/extension/sw-extension-icon';
import 'src/app/component/base/sw-container';
import 'src/app/component/base/sw-button-process';
import 'src/app/component/base/sw-button';
import 'src/app/component/base/sw-modal';
import 'src/app/component/form/sw-select-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/field-base/sw-field-error';
import 'src/app/component/form/sw-password-field';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/field-base/sw-contextual-field';

Shopware.Component.register('sw-first-run-wizard-welcome', swFirstRunWizardWelcome);
Shopware.Component.register('sw-plugin-card', swPluginCard);
Shopware.Component.register('sw-extension-icon', swExtensionIcon);

const setLocaleWithIdMock = jest.fn(() => Promise.resolve({}));

Shopware.Service().register('localeHelper', () => {
    return {
        setLocaleWithId: setLocaleWithIdMock,
    };
});

const languagePlugins = {
    items: [{
        extensions: [],
        name: 'SwagLanguagePack',
        label: 'Shopware Language Pack',
        // eslint-disable-next-line max-len
        shortDescription: 'With all languages in one extension, switching languages in your online shop has never been easier! Simply choose the languages for your admin and storefront for you and your customers.',
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
    }],
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
    }, {
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
 * @package merchant-services
 */
describe('src/module/sw-first-run-wizard/view/sw-first-run-wizard-welcome', () => {
    async function createWrapper() {
        return shallowMount(await Shopware.Component.build('sw-first-run-wizard-welcome'), {
            stubs: {
                'sw-container': await Shopware.Component.build('sw-container'),
                'sw-plugin-card': await Shopware.Component.build('sw-plugin-card'),
                'sw-button-process': await Shopware.Component.build('sw-button-process'),
                'sw-button': await Shopware.Component.build('sw-button'),
                'sw-modal': await Shopware.Component.build('sw-modal'),
                'sw-select-field': await Shopware.Component.build('sw-select-field'),
                'sw-block-field': await Shopware.Component.build('sw-block-field'),
                'sw-base-field': await Shopware.Component.build('sw-base-field'),
                'sw-field-error': await Shopware.Component.build('sw-field-error'),
                'sw-password-field': await Shopware.Component.build('sw-password-field'),
                'sw-text-field': await Shopware.Component.build('sw-text-field'),
                'sw-contextual-field': await Shopware.Component.build('sw-contextual-field'),
                'sw-icon': true,
                'sw-loader': true,
                'sw-extension-icon': await Shopware.Component.build('sw-extension-icon'),
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
        });
    }

    it('should install the SwagLanguagePack plugin and show the language switch modal', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.findAll('.sw-plugin-card')).toHaveLength(1);

        await wrapper.find('.button-plugin-install').trigger('click');
        await flushPromises();
        await wrapper.vm.$nextTick();

        const modal = await wrapper.find('.sw-first-run-wizard-confirmLanguageSwitch-modal');
        expect(modal.isVisible()).toBe(true);

        const languageSelect = await modal.find('[name=sw-field--user-localeId]');
        await languageSelect.findAll('option').at(1).setSelected();
        const selectedLanguage = languageSelect.find('option:checked').element.value;
        await modal.find('[name=sw-field--user-pw]').setValue('p4ssw0rd');
        await modal.find('.sw-button--primary').trigger('click');

        expect(setLocaleWithIdMock).toHaveBeenCalledWith(selectedLanguage);
    });
});
