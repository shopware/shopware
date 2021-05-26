import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-product/component/sw-product-bulk-edit-modal';

const CURRENCY_ID = {
    EURO: 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
    POUND: 'fce3465831e8639bb2ea165d0fcf1e8b'
};

function mockPrices() {
    return [
        {
            currencyId: CURRENCY_ID.POUND,
            net: 373.83,
            gross: 400,
            linked: true
        },
        {
            currencyId: CURRENCY_ID.EURO,
            net: 560.75,
            gross: 600,
            linked: true
        }
    ];
}

function createWrapper() {
    return shallowMount(Shopware.Component.build('sw-product-bulk-edit-modal'), {
        propsData: {
            bulkGridEditColumns: [],
            currencies: []
        },
        provide: {
        },
        stubs: {
            'sw-bulk-edit-modal': {
                template: '<div></div>'
            }
        }
    });
}

describe('module/sw-product/component/sw-product-bulk-edit-modal', () => {
    let wrapper;

    beforeEach(() => {
        wrapper = createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should return price when given currency id', async () => {
        const currencyId = CURRENCY_ID.EURO;
        const prices = mockPrices();

        const foundPriceData = wrapper.vm.getCurrencyPriceByCurrencyId(currencyId, prices);
        const expectedPriceData = {
            currencyId: CURRENCY_ID.EURO,
            net: 560.75,
            gross: 600,
            linked: true
        };

        expect(foundPriceData).toEqual(expectedPriceData);
    });

    it('should return fallback when no price was found', async () => {
        const currencyId = 'no-valid-id';
        const prices = mockPrices();

        const foundPriceData = wrapper.vm.getCurrencyPriceByCurrencyId(currencyId, prices);
        const expectedPriceData = {
            currencyId: null,
            gross: null,
            linked: true,
            net: null
        };

        expect(foundPriceData).toEqual(expectedPriceData);
    });
});
