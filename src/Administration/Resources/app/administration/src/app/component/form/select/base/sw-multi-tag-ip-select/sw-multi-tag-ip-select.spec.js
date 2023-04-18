import { shallowMount } from '@vue/test-utils';
import 'src/app/component/form/select/base/sw-multi-tag-select';
import 'src/app/component/form/select/base/sw-multi-tag-ip-select';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/field-base/sw-field-error';
import 'src/app/component/form/select/base/sw-select-selection-list';
import 'src/app/component/utils/sw-popover';

const createMultiDataIpSelect = async (customOptions) => {
    const options = {
        stubs: {
            'sw-select-base': await Shopware.Component.build('sw-select-base'),
            'sw-block-field': await Shopware.Component.build('sw-block-field'),
            'sw-base-field': await Shopware.Component.build('sw-base-field'),
            'sw-field-error': await Shopware.Component.build('sw-field-error'),
            'sw-select-selection-list': await Shopware.Component.build('sw-select-selection-list'),
            'sw-popover': await Shopware.Component.build('sw-popover'),
            'sw-icon': {
                template: '<div></div>',
            },
        },
        propsData: {
            value: [],
        },
    };

    return shallowMount(await Shopware.Component.build('sw-multi-tag-ip-select'), {
        ...options,
        ...customOptions,
    });
};

describe('components/sw-multi-tag-ip-select', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createMultiDataIpSelect();
        expect(wrapper.vm).toBeTruthy();
    });

    [
        ['a676344c-c0dd-49e5-8fbb-5f570c27762c', false],
        ['::', true],
        ['10.0.0.1', true],
        ['aabb::', true],
        ['127.0.0.1abcd', false],
    ].forEach(([value, shouldBeValid]) => {
        it(`should validate IPs correctly: ${value} should be ${shouldBeValid}`, async () => {
            const multiDataIpSelect = await createMultiDataIpSelect();
            const input = multiDataIpSelect.find('.sw-select-selection-list__input');

            expect(multiDataIpSelect.vm.inputIsValid).toBeFalsy();
            expect(multiDataIpSelect.vm.errorObject).toBeNull();

            await input.setValue(value);

            expect(multiDataIpSelect.vm.searchTerm).toBe(value.toString());
            expect(multiDataIpSelect.vm.inputIsValid).toBe(shouldBeValid);

            await input.setValue('');

            expect(multiDataIpSelect.vm.inputIsValid).toBeFalsy();
            expect(multiDataIpSelect.vm.errorObject).toBeNull();
        });
    });
});
