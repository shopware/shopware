import 'src/app/component/filter/sw-existence-filter';
import 'src/app/component/filter/sw-base-filter';
import 'src/app/component/form/sw-select-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import { createLocalVue, shallowMount } from '@vue/test-utils';

const { Criteria } = Shopware.Data;

function createWrapper() {
    const localVue = createLocalVue();

    return shallowMount(Shopware.Component.build('sw-existence-filter'), {
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
                property: 'media',
                name: 'media',
                label: 'Product without images',
                schema: {
                    localField: 'id'
                }
            },
            active: true
        },
        mocks: {
            $tc: key => key,
            $t: key => key
        },
        provide: {}
    });
}

describe('components/sw-existence-filter', () => {
    it('should emit `filter-update` event when user changes from unset to `true`', async () => {
        const wrapper = createWrapper();

        const options = wrapper.find('select').findAll('option');

        options.at(0).setSelected();

        expect(wrapper.emitted()['filter-update'][0]).toEqual([
            'media',
            [Criteria.not('AND', [Criteria.equals('media.id', null)])],
            'true'
        ]);
    });

    it('should emit `filter-update` event when user changes from default option to `false`', async () => {
        const wrapper = createWrapper();

        const options = wrapper.find('select').findAll('option');

        options.at(1).setSelected();

        expect(wrapper.emitted()['filter-update'][0]).toEqual([
            'media',
            [Criteria.equals('media.id', null)],
            'false'
        ]);
    });

    it('should emit `filter-reset` event when user clicks Reset button from `true`', async () => {
        const wrapper = createWrapper();

        await wrapper.setProps({ filter: { ...wrapper.vm.filter, value: 'true' } });

        // Trigger click Reset button
        wrapper.find('.sw-base-filter__reset').trigger('click');

        expect(wrapper.emitted()['filter-reset']).toBeTruthy();
    });

    it('should emit `filter-reset` event when user clicks Reset button from `false`', async () => {
        const wrapper = createWrapper();

        await wrapper.setProps({ filter: { ...wrapper.vm.filter, value: 'false' } });

        // Trigger click Reset button
        wrapper.find('.sw-base-filter__reset').trigger('click');

        expect(wrapper.emitted()['filter-reset']).toBeTruthy();
    });

    it('should emit `filter-update` event when user changes from `true` to `false`', async () => {
        const wrapper = createWrapper();

        await wrapper.setProps({ filter: { ...wrapper.vm.filter, value: 'true' } });

        const options = wrapper.find('select').findAll('option');

        options.at(1).setSelected();

        expect(wrapper.emitted()['filter-update'][0]).toEqual([
            'media',
            [Criteria.equals('media.id', null)],
            'false'
        ]);
    });

    it('should emit `filter-update` event when user changes from `false` to `true`', async () => {
        const wrapper = createWrapper();

        await wrapper.setProps({ filter: { ...wrapper.vm.filter, value: 'false' } });

        const options = wrapper.find('select').findAll('option');

        options.at(0).setSelected();

        expect(wrapper.emitted()['filter-update'][0]).toEqual([
            'media',
            [Criteria.not('AND', [Criteria.equals('media.id', null)])],
            'true'
        ]);
    });

    it('should reset the filter value when `active` is false', async () => {
        const wrapper = createWrapper();

        const options = wrapper.find('select').findAll('option');

        options.at(0).setSelected();

        await wrapper.setProps({ active: false });

        expect(wrapper.emitted()['filter-reset']).toBeTruthy();
    });

    it('should not reset the filter value when `active` is true', async () => {
        const wrapper = createWrapper();

        const options = wrapper.find('select').findAll('option');

        options.at(0).setSelected();

        await wrapper.setProps({ active: true });

        expect(wrapper.emitted()['filter-reset']).toBeFalsy();
    });

    it('should emit `filter-update` event with correct value when filter has no entity', async () => {
        const wrapper = createWrapper();

        wrapper.setProps({
            filter: {
                property: 'media',
                name: 'media',
                label: 'Product without images',
                optionHasCriteria: 'Has media',
                optionNoCriteria: 'No media'
            }
        });

        const options = wrapper.find('select').findAll('option');

        options.at(0).setSelected();

        expect(wrapper.emitted()['filter-update'][0]).toEqual([
            'media',
            [Criteria.not('AND', [Criteria.equals('media', null)])],
            'true'
        ]);
    });
});
