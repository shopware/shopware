/**
 * @package buyers-experience
 * @group disabledCompat
 */

import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-settings-currency-country-modal', {
        sync: true,
    }), {
        props: {
            currencyCountryRounding: {
                currencyId: 'currencyId1',
            },
        },
        global: {
            provide: {
                repositoryFactory: {
                    create: () => {
                        return {
                            searchIds: () => Promise.resolve([]),
                        };
                    },
                },
            },
            stubs: {
                'sw-modal': true,
                'sw-entity-single-select': true,
                'sw-settings-price-rounding': true,
                'sw-button': true,
                'sw-highlight-text': true,
                'sw-select-result': true,
            },
        },
    });
}

describe('module/sw-settings-currency/component/sw-settings-currency-country-modal', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should disable already assigned countries', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData({
            assignedCountryIds: ['countryId1'],
        });

        expect(wrapper.vm.shouldDisableCountry({ id: 'countryId1' })).toBe(true);
        expect(wrapper.vm.shouldDisableCountry({ id: 'countryId2' })).toBe(false);
    });

    it('should not disable country if it is already assigned(edit)', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            currencyCountryRounding: {
                currencyId: 'currencyId1',
                countryId: 'countryId1',
            },
        });
        await wrapper.setData({
            assignedCountryIds: ['countryId1'],
        });

        expect(wrapper.vm.shouldDisableCountry({ id: 'countryId1' })).toBe(false);
        expect(wrapper.vm.shouldDisableCountry({ id: 'countryId2' })).toBe(false);
    });
});

