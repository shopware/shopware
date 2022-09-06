import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-settings-tax/component/sw-settings-tax-rule-modal';
import 'src/app/component/base/sw-modal';
import 'src/app/component/base/sw-button';

function createWrapper() {
    const localVue = createLocalVue();

    return shallowMount(Shopware.Component.build('sw-settings-tax-rule-modal'), {
        localVue,
        stubs: {
            'sw-modal': Shopware.Component.build('sw-modal'),
            'sw-button': Shopware.Component.build('sw-button'),
            'sw-icon': true,
            'sw-container': true,
            'sw-entity-single-select': true,
            'sw-number-field': true,
        },
        provide: {
            shortcutService: {
                stopEventListener: () => {},
                startEventListener: () => {},
            },
        },
        propsData: {
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

    beforeEach(() => {
        wrapper = createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should house a country criteria', () => {
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
});
