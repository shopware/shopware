import 'src/app/component/filter/sw-boolean-filter';
import 'src/app/component/filter/sw-base-filter';
import 'src/app/component/form/sw-select-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import { createLocalVue, shallowMount } from '@vue/test-utils';

const { Criteria } = Shopware.Data;

function createWrapper() {
    const localVue = createLocalVue();

    return shallowMount(Shopware.Component.build('sw-boolean-filter'), {
        localVue,
        stubs: {
            'sw-block-field': Shopware.Component.build('sw-block-field'),
            'sw-base-field': Shopware.Component.build('sw-base-field'),
            'sw-select-field': Shopware.Component.build('sw-select-field'),
            'sw-base-filter': Shopware.Component.build('sw-base-filter'),
            'sw-icon': true,
            'sw-field-error': {
                template: '<div></div>'
            }
        },
        propsData: {
            filter: {
                property: 'manufacturerId',
                name: 'manufacturerId',
                label: 'Manufacturer ID'
            }
        },
        mocks: {
            $tc: key => key
        },
        provide: {}
    });
}

describe('components/sw-boolean-filter', () => {
    it('should emit `updateFilter` event when user changes from default option to `Active`', async () => {
        const wrapper = createWrapper();

        const options = wrapper.find('select').findAll('option');

        options.at(0).setSelected();

        expect(wrapper.emitted().updateFilter[0]).toEqual([
            'manufacturerId',
            [Criteria.equals('manufacturerId', true)]
        ]);
    });

    it('should emit `updateFilter` event when user changes from default option to `Inactive`', async () => {
        const wrapper = createWrapper();

        const options = wrapper.find('select').findAll('option');

        options.at(1).setSelected();

        expect(wrapper.emitted().updateFilter[0]).toEqual([
            'manufacturerId',
            [Criteria.equals('manufacturerId', false)]
        ]);
    });

    it('should emit `resetFilter` event when user clicks Reset button from `Active` option', async () => {
        const wrapper = createWrapper();

        await wrapper.setData({ value: 'true' });

        // Trigger click Reset button
        wrapper.find('.sw-base-filter__reset').trigger('click');

        expect(wrapper.emitted().resetFilter).toBeTruthy();
    });

    it('should emit `resetFilter` event when user clicks Reset button from `Inactive` option', async () => {
        const wrapper = createWrapper();

        await wrapper.setData({ value: 'false' });

        // Trigger click Reset button
        wrapper.find('.sw-base-filter__reset').trigger('click');

        expect(wrapper.emitted().resetFilter).toBeTruthy();
    });
    //

    it('should emit `updateFilter` event when user changes from `Active` to `Inactive`', async () => {
        const wrapper = createWrapper();

        await wrapper.setData({ value: 'true' });

        const options = wrapper.find('select').findAll('option');

        options.at(1).setSelected();

        expect(wrapper.emitted().updateFilter[0]).toEqual([
            'manufacturerId',
            [Criteria.equals('manufacturerId', false)]
        ]);
    });

    it('should emit `updateFilter` event when user changes from `Inactive` to `Active`', async () => {
        const wrapper = createWrapper();

        await wrapper.setData({ value: 'false' });

        const options = wrapper.find('select').findAll('option');

        options.at(0).setSelected();

        expect(wrapper.emitted().updateFilter[0]).toEqual([
            'manufacturerId',
            [Criteria.equals('manufacturerId', true)]
        ]);
    });
});
