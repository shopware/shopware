import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/app/component/base/sw-simple-search-field';
import 'src/app/component/form/sw-field';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/base/sw-icon';
import 'src/app/component/form/field-base/sw-field-error';

function createWrapper(additionalOptions = {}) {
    const localVue = createLocalVue();
    return shallowMount(Shopware.Component.build('sw-simple-search-field'), {
        localVue,
        stubs: {
            'sw-field': Shopware.Component.build('sw-field'),
            'sw-text-field': Shopware.Component.build('sw-text-field'),
            'sw-contextual-field': Shopware.Component.build('sw-contextual-field'),
            'sw-block-field': Shopware.Component.build('sw-block-field'),
            'sw-base-field': Shopware.Component.build('sw-base-field'),
            'sw-field-error': Shopware.Component.build('sw-field-error'),
            'sw-icon': Shopware.Component.build('sw-icon'),
            'icons-small-search': true
        },
        provide: {
            validationService: {}
        },
        propsData: {
            delay: 1,
            searchTerm: 'search term'
        },
        ...additionalOptions
    });
}

describe('components/base/sw-simple-search-field', () => {
    /** @type Wrapper */
    let wrapper;

    beforeAll(() => {
        wrapper = createWrapper();
    });

    afterAll(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });
    it('should have `search term` as initial value', async () => {
        expect(wrapper.find('input[type="text"]').element.value).toBe('search term');
    });

    it('should emit `search-term-change` event', async () => {
        await wrapper.find('input[type="text"]')
            .setValue('@searchTermChange Sw Simple Search Field Typing');

        // search-term-change event is debounced
        expect(wrapper.emitted()['search-term-change']).toBeFalsy();
        wrapper.vm.onSearchTermChanged.flush();
        // now it should exist
        expect(wrapper.emitted()['search-term-change']).toBeTruthy();
    });
});
