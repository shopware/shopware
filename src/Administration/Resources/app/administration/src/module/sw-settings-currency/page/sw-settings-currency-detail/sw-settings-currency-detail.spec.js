/**
 * @package buyers-experience
 */
import { shallowMount } from '@vue/test-utils';
import swSettingsCurrencyDetail from 'src/module/sw-settings-currency/page/sw-settings-currency-detail';

Shopware.Component.register('sw-settings-currency-detail', swSettingsCurrencyDetail);

async function createWrapper(privileges = []) {
    return shallowMount(await Shopware.Component.build('sw-settings-currency-detail'), {
        provide: {
            repositoryFactory: {
                create: () => ({
                    create: () => {
                        return {
                            name: '',
                            isoCode: '',
                            shortName: '',
                            symbol: '',
                            factor: 1,
                            decimalPrecision: 1,
                        };
                    },
                }),
            },
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                },
            },
            customFieldDataProviderService: {
                getCustomFieldSets: () => Promise.resolve([]),
            },
        },
        stubs: {
            'sw-page': true,
            'sw-button': true,
            'sw-button-process': true,
            'sw-language-switch': true,
            'sw-card-view': true,
            'sw-card': true,
            'sw-container': true,
            'sw-text-field': true,
            'sw-number-field': true,
            'sw-language-info': true,
            'sw-settings-price-rounding': true,
            'sw-empty-state': true,
            'sw-skeleton': true,
        },
    });
}

describe('module/sw-settings-currency/page/sw-settings-currency-detail', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should not be able to save the currency', async () => {
        const wrapper = await createWrapper();

        const saveButton = wrapper.find('.sw-settings-currency-detail__save-action');

        expect(saveButton.attributes().disabled).toBeTruthy();
    });

    it('should be able to save the currency', async () => {
        const wrapper = await createWrapper([
            'currencies.editor',
        ]);

        const saveButton = wrapper.find('.sw-settings-currency-detail__save-action');

        expect(saveButton.attributes().disabled).toBeFalsy();
    });
});

