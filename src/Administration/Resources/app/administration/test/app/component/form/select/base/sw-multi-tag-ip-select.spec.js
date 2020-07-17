import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/app/component/form/select/base/sw-multi-tag-select';
import 'src/app/component/form/select/base/sw-multi-tag-ip-select';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/field-base/sw-field-error';
import 'src/app/component/form/select/base/sw-select-selection-list';
import 'src/app/component/utils/sw-popover';

const selector = {
    multiDataIpSelect: {
        input: '.sw-select-selection-list__input'
    }
};

const createMultiDataIpSelect = (customOptions) => {
    const localVue = createLocalVue();
    localVue.directive('popover', {});

    const options = {
        localVue,
        stubs: {
            'sw-select-base': Shopware.Component.build('sw-select-base'),
            'sw-block-field': Shopware.Component.build('sw-block-field'),
            'sw-base-field': Shopware.Component.build('sw-base-field'),
            'sw-field-error': Shopware.Component.build('sw-field-error'),
            'sw-select-selection-list': Shopware.Component.build('sw-select-selection-list'),
            'sw-popover': Shopware.Component.build('sw-popover'),
            'sw-icon': '<div></div>'
        },
        mocks: { $tc: key => key },
        propsData: {
            value: []
        }
    };

    return shallowMount(Shopware.Component.build('sw-multi-tag-ip-select'), {
        ...options,
        ...customOptions
    });
};

describe('components/sw-multi-tag-ip-select', () => {
    it('should be a Vue.js component', () => {
        expect(createMultiDataIpSelect().isVueInstance()).toBeTruthy();
    });

    it('should validate IPs correctly', () => {
        const testCases = new Map([
            ['a676344c-c0dd-49e5-8fbb-5f570c27762c', false],
            ['::', true],
            ['10.0.0.1', true],
            ['aabb::', true],
            ['127.0.0.1abcd', false]
        ]);

        const multiDataIpSelect = createMultiDataIpSelect();
        const input = multiDataIpSelect.find(selector.multiDataIpSelect.input);

        expect(multiDataIpSelect.vm.inputIsValid).toBeFalsy();
        expect(multiDataIpSelect.vm.errorObject).toBeNull();

        testCases.forEach((shouldBeValid, value) => {
            input.setValue(value);

            expect(multiDataIpSelect.vm.searchTerm).toBe(value.toString());
            expect(multiDataIpSelect.vm.inputIsValid).toBe(shouldBeValid);
        });

        input.setValue('');

        expect(multiDataIpSelect.vm.inputIsValid).toBeFalsy();
        expect(multiDataIpSelect.vm.errorObject).toBeNull();
    });
});
