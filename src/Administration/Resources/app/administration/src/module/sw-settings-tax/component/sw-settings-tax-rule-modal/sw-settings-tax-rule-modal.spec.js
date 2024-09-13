import { mount } from '@vue/test-utils';

/**
 * @package customer-order
 */
async function createWrapper() {
    return mount(await wrapTestComponent('sw-settings-tax-rule-modal', {
        sync: true,
    }), {
        global: {
            renderStubDefaultSlot: true,
            stubs: {
                'sw-modal': await wrapTestComponent('sw-modal'),
                'sw-button': await wrapTestComponent('sw-button'),
                'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated'),
                'sw-icon': true,
                'sw-container': true,
                'sw-entity-single-select': true,
                'sw-number-field': true,
                'sw-datepicker': true,
                'sw-loader': true,
                'router-link': true,
            },
            provide: {
                shortcutService: {
                    stopEventListener: () => {},
                    startEventListener: () => {},
                },
            },
        },
        props: {
            tax: {
                id: '55a0817ba3314dd2a1ba4d94fe74a72b',
                name: 'Standard rate',
                position: 1,
            },
            currentRule: {
                countryId: '29a0a053e55947888c350caa48bf4f1d',
                country: {
                    id: '29a0a053e55947888c350caa48bf4f1d',
                    name: 'VietNam',
                },
            },
        },
    });
}

describe('sw-settings-tax-rule-modal', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should house a country criteria', async () => {
        expect(wrapper.vm.countryCriteria).toEqual(expect.objectContaining({
            page: 1,
            limit: 25,
            sortings: expect.arrayContaining([
                expect.objectContaining({
                    field: 'name',
                    order: 'ASC',
                    naturalSorting: false,
                }),
            ]),
        }));
    });

    it('should have a tax rate field with a correct "digits" property', async () => {
        await flushPromises();
        const taxRateField = wrapper.find(
            'sw-number-field-stub[label="sw-settings-tax.taxRuleCard.labelTaxRate"]',
        );

        expect(taxRateField.attributes('digits')).toBe('3');
    });
});
