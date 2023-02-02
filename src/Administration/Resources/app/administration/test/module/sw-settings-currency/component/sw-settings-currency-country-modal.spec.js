import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-settings-currency/component/sw-settings-currency-country-modal';

function createWrapper() {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-settings-currency-country-modal'), {
        localVue,
        propsData: {
            currencyCountryRounding: {
                currencyId: 'currencyId1'
            }
        },
        provide: {
            repositoryFactory: {
                create: () => {
                    return {
                        searchIds: () => Promise.resolve([])
                    };
                }
            }
        },
        stubs: {
            'sw-modal': true,
            'sw-entity-single-select': true,
            'sw-settings-price-rounding': true,
            'sw-button': true
        }
    });
}

describe('module/sw-settings-currency/component/sw-settings-currency-country-modal', () => {
    it('should be a Vue.JS component', () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should disable already assigned countries', () => {
        const wrapper = createWrapper();
        wrapper.setData({
            assignedCountryIds: ['countryId1']
        });

        expect(wrapper.vm.shouldDisableCountry({ id: 'countryId1' })).toBe(true);
        expect(wrapper.vm.shouldDisableCountry({ id: 'countryId2' })).toBe(false);
    });

    it('should not disable country if it is already assigned(edit)', () => {
        const wrapper = createWrapper();
        wrapper.setData({
            assignedCountryIds: ['countryId1']
        });
        wrapper.setProps({
            currencyCountryRounding: {
                currencyId: 'currencyId1',
                countryId: 'countryId1'
            }
        });

        expect(wrapper.vm.shouldDisableCountry({ id: 'countryId1' })).toBe(false);
        expect(wrapper.vm.shouldDisableCountry({ id: 'countryId2' })).toBe(false);
    });
});

