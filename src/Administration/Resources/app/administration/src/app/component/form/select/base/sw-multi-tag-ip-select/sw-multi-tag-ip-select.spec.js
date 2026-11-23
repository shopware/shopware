/**
 * @sw-package framework
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
                'sw-popover-deprecated': await wrapTestComponent('sw-popover-deprecated', { sync: true }),
                'sw-loader': true,
                'sw-inheritance-switch': true,
                'sw-ai-copilot-badge': true,
                'sw-help-text': true,
                'sw-label': true,
            },
        },
        props: {
            value: [],
        },
    });
};

describe('components/sw-multi-tag-ip-select', () => {
    it.each([
        ['an invalid value', '127.0.0.1abcd', 'global.sw-multi-tag-ip-select.enterValidIp'],
        ['a valid value', '10.0.0.1', 'global.sw-multi-tag-ip-select.addIpAddress'],
    ])('tells the user what to do with %s', async (_case, value, message) => {
        const multiDataIpSelect = await createMultiDataIpSelect();
        await flushPromises();

        await multiDataIpSelect.find('.sw-select__selection').trigger('click');
        await multiDataIpSelect.find('.sw-select-selection-list__input').setValue(value);
        await flushPromises();

        expect(document.body.querySelector('.sw-select-result-list__content').textContent.trim()).toBe(message);
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
    });
});
