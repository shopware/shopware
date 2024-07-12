/**
 * @group disabledCompat
 */
import { mount } from '@vue/test-utils';

const { Criteria } = Shopware.Data;

async function createWrapper() {
    return mount(await wrapTestComponent('sw-boolean-filter', { sync: true }), {
        global: {
            stubs: {
                'sw-block-field': await wrapTestComponent('sw-block-field', { sync: true }),
                'sw-base-field': await wrapTestComponent('sw-base-field', { sync: true }),
                'sw-select-field': await wrapTestComponent('sw-select-field', { sync: true }),
                'sw-select-field-deprecated': await wrapTestComponent('sw-select-field-deprecated', { sync: true }),
                'sw-base-filter': await wrapTestComponent('sw-base-filter', { sync: true }),
                'sw-icon': true,
                'sw-help-text': true,
                'sw-ai-copilot-badge': true,
                'sw-inheritance-switch': true,
                'sw-loader': true,
                'mt-select': true,
                'sw-field-error': {
                    template: '<div></div>',
                },
            },
        },
        props: {
            filter: {
                property: 'manufacturerId',
                name: 'manufacturerId',
                label: 'Manufacturer ID',
                filterCriteria: null,
                value: null,
            },
            active: true,
        },
    });
}

describe('components/sw-boolean-filter', () => {
    it('should emit `filter-update` event when user changes from default option to `Active`', async () => {
        const wrapper = await createWrapper();

        const options = wrapper.find('select').findAll('option');

        await options.at(0).setSelected();

        expect(wrapper.emitted()['filter-update'][0]).toEqual([
            'manufacturerId',
            [Criteria.equals('manufacturerId', true)],
            'true',
        ]);
    });

    it('should emit `filter-update` event when user changes from default option to `Inactive`', async () => {
        const wrapper = await createWrapper();

        const options = wrapper.find('select').findAll('option');

        await options.at(1).setSelected();

        expect(wrapper.emitted()['filter-update'][0]).toEqual([
            'manufacturerId',
            [Criteria.equals('manufacturerId', false)],
            'false',
        ]);
    });

    it('should emit `filter-reset` event when user clicks Reset button from `Active` option', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({ filter: { ...wrapper.vm.filter, value: 'true' } });

        // Trigger click Reset button
        await wrapper.find('.sw-base-filter__reset').trigger('click');

        expect(wrapper.emitted()['filter-reset']).toBeTruthy();
    });

    it('should emit `filter-reset` event when user clicks Reset button from `Inactive` option', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({ filter: { ...wrapper.vm.filter, value: 'false' } });

        // Trigger click Reset button
        await wrapper.find('.sw-base-filter__reset').trigger('click');

        expect(wrapper.emitted()['filter-reset']).toBeTruthy();
    });
    //

    it('should emit `filter-update` event when user changes from `Active` to `Inactive`', async () => {
        const wrapper = await createWrapper();

        await wrapper.get('.sw-block-field__block').trigger('click');

        const options = wrapper.find('select').findAll('option');

        await options.at(1).setSelected();

        expect(wrapper.emitted()['filter-update'][0]).toEqual([
            'manufacturerId',
            [Criteria.equals('manufacturerId', false)],
            'false',
        ]);
    });

    it('should emit `filter-update` event when user changes from `Inactive` to `Active`', async () => {
        const wrapper = await createWrapper();

        await wrapper.get('.sw-block-field__block').trigger('click');

        const options = wrapper.find('select').findAll('option');

        await options.at(0).setSelected();

        expect(wrapper.emitted()['filter-update'][0]).toEqual([
            'manufacturerId',
            [Criteria.equals('manufacturerId', true)],
            'true',
        ]);
    });

    it('should reset the filter value when `active` is false', async () => {
        const wrapper = await createWrapper();

        const options = wrapper.find('select').findAll('option');

        await options.at(0).setSelected();

        await wrapper.setProps({ active: false });

        expect(wrapper.vm.value).toBeNull();
        expect(wrapper.vm.filter.value).toBeNull();
        expect(wrapper.emitted()['filter-reset']).toBeTruthy();
    });

    it('should not reset the filter value when `active` is true', async () => {
        const wrapper = await createWrapper();

        const options = wrapper.find('select').findAll('option');

        await options.at(0).setSelected();

        await wrapper.setProps({ active: true });

        expect(wrapper.emitted()['filter-reset']).toBeFalsy();
    });
});
