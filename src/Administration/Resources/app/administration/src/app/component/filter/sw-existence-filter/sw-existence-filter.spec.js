/**
 * @group disabledCompat
 */
import 'src/app/component/filter/sw-existence-filter';
import 'src/app/component/filter/sw-base-filter';
import 'src/app/component/form/sw-select-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import { mount } from '@vue/test-utils';

const { Criteria } = Shopware.Data;

async function createWrapper() {
    return mount(await wrapTestComponent('sw-existence-filter', { sync: true }), {
        global: {
            stubs: {
                'sw-block-field': await wrapTestComponent('sw-block-field', { sync: true }),
                'sw-base-field': await wrapTestComponent('sw-base-field', { sync: true }),
                'sw-select-field': await wrapTestComponent('sw-select-field', { sync: true }),
                'sw-select-field-deprecated': await wrapTestComponent('sw-select-field-deprecated', { sync: true }),
                'sw-base-filter': await wrapTestComponent('sw-base-filter', { sync: true }),
                'sw-icon': true,
                'sw-field-error': {
                    template: '<div></div>',
                },
                'sw-help-text': true,
                'sw-ai-copilot-badge': true,
                'sw-inheritance-switch': true,
                'sw-loader': true,
                'mt-select': true,
            },
        },
        props: {
            filter: {
                property: 'media',
                name: 'media',
                label: 'Product without images',
                schema: {
                    localField: 'id',
                },
                optionHasCriteria: true,
                optionNoCriteria: false,
            },
            active: true,
        },
    });
}

describe('components/sw-existence-filter', () => {
    it('should emit `filter-update` event when user changes from unset to `true`', async () => {
        const wrapper = await createWrapper();

        const options = wrapper.find('select').findAll('option');

        await options.at(0).setSelected();

        expect(wrapper.emitted()['filter-update'][0]).toEqual([
            'media',
            [Criteria.not('AND', [Criteria.equals('media.id', null)])],
            'true',
        ]);
    });

    it('should emit `filter-update` event when user changes from default option to `false`', async () => {
        const wrapper = await createWrapper();

        const options = wrapper.find('select').findAll('option');

        await options.at(1).setSelected();

        expect(wrapper.emitted()['filter-update'][0]).toEqual([
            'media',
            [Criteria.equals('media.id', null)],
            'false',
        ]);
    });

    it('should emit `filter-reset` event when user clicks Reset button from `true`', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({ filter: { ...wrapper.vm.filter, value: 'true' } });

        // Trigger click Reset button
        await wrapper.find('.sw-base-filter__reset').trigger('click');

        expect(wrapper.emitted()['filter-reset']).toBeTruthy();
    });

    it('should emit `filter-reset` event when user clicks Reset button from `false`', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({ filter: { ...wrapper.vm.filter, value: 'false' } });

        // Trigger click Reset button
        await wrapper.find('.sw-base-filter__reset').trigger('click');

        expect(wrapper.emitted()['filter-reset']).toBeTruthy();
    });

    it('should emit `filter-update` event when user changes from `true` to `false`', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({ filter: { ...wrapper.vm.filter, value: 'true' } });

        const options = wrapper.find('select').findAll('option');

        await options.at(1).setSelected();

        expect(wrapper.emitted()['filter-update'][0]).toEqual([
            'media',
            [Criteria.equals('media.id', null)],
            'false',
        ]);
    });

    it('should emit `filter-update` event when user changes from `false` to `true`', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({ filter: { ...wrapper.vm.filter, value: 'false' } });

        const options = wrapper.find('select').findAll('option');

        await options.at(0).setSelected();

        expect(wrapper.emitted()['filter-update'][0]).toEqual([
            'media',
            [Criteria.not('AND', [Criteria.equals('media.id', null)])],
            'true',
        ]);
    });

    it('should reset the filter value when `active` is false', async () => {
        const wrapper = await createWrapper();

        const options = wrapper.find('select').findAll('option');

        await options.at(0).setSelected();

        await wrapper.setProps({ active: false });

        expect(wrapper.emitted()['filter-reset']).toBeTruthy();
    });

    it('should not reset the filter value when `active` is true', async () => {
        const wrapper = await createWrapper();

        const options = wrapper.find('select').findAll('option');

        await options.at(0).setSelected();

        await wrapper.setProps({ active: true });

        expect(wrapper.emitted()['filter-reset']).toBeFalsy();
    });

    it('should emit `filter-update` event with correct value when filter has no entity', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            filter: {
                property: 'media',
                name: 'media',
                label: 'Product without images',
                optionHasCriteria: 'Has media',
                optionNoCriteria: 'No media',
            },
        });

        const options = wrapper.find('select').findAll('option');

        await options.at(0).setSelected();

        expect(wrapper.emitted()['filter-update'][0]).toEqual([
            'media',
            [Criteria.not('AND', [Criteria.equals('media', null)])],
            'true',
        ]);
    });
});
