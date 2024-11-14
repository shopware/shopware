/**
 * @package admin
 */
import { mount } from '@vue/test-utils';

const createMultiDataIpSelect = async () => {
    return mount(await wrapTestComponent('sw-multi-tag-ip-select', { sync: true }), {
        global: {
            stubs: {
                'sw-select-base': await wrapTestComponent('sw-select-base'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-field-error': await wrapTestComponent('sw-field-error'),
                'sw-select-selection-list': await wrapTestComponent('sw-select-selection-list'),
                'sw-popover': await wrapTestComponent('sw-popover'),
                'sw-icon': {
                    template: '<div></div>',
                },
                'sw-loader': true,
                'sw-inheritance-switch': true,
                'sw-ai-copilot-badge': true,
                'sw-help-text': true,
                'sw-label': true,
                'sw-button': true,
            },
        },
        props: {
            value: [],
        },
    });
};

describe('components/sw-multi-tag-ip-select', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createMultiDataIpSelect();
        expect(wrapper.vm).toBeTruthy();
    });

    [
        [
            'a676344c-c0dd-49e5-8fbb-5f570c27762c',
            false,
        ],
        [
            '::',
            true,
        ],
        [
            '10.0.0.1',
            true,
        ],
        [
            'aabb::',
            true,
        ],
        [
            '127.0.0.1abcd',
            false,
        ],
    ].forEach(
        ([
            value,
            shouldBeValid,
        ]) => {
            it(`should validate IPs correctly: ${value} should be ${shouldBeValid}`, async () => {
                const multiDataIpSelect = await createMultiDataIpSelect();
                await flushPromises();

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
        },
    );
});
