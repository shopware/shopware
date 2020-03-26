import { createLocalVue, shallowMount } from '@vue/test-utils';

const createSingleSelect = (customOptions) => {
    const localVue = createLocalVue();
    localVue.directive('popover', {});

    const options = {
        localVue,
        stubs: {
            'sw-select-base': Shopware.Component.build('sw-select-base'),
            'sw-block-field': Shopware.Component.build('sw-block-field'),
            'sw-base-field': Shopware.Component.build('sw-base-field'),
            'sw-icon': '<div></div>',
            'sw-field-error': Shopware.Component.build('sw-field-error'),
            'sw-select-result-list': Shopware.Component.build('sw-select-result-list'),
            'sw-popover': Shopware.Component.build('sw-popover'),
            'sw-select-result': Shopware.Component.build('sw-select-result'),
            'sw-highlight-text': Shopware.Component.build('sw-highlight-text')
        },
        mocks: { $tc: key => key },
        propsData: {
            value: null,
            options: [
                {
                    label: 'Entry 1',
                    value: 'entryOneValue'
                },
                {
                    label: 'Entry 2',
                    value: 'entryTwoValue'
                },
                {
                    label: 'Entry 3',
                    value: 'entryThreeValue'
                }
            ]
        }
    };

    return shallowMount(Shopware.Component.build('sw-import-export-entity-path-select'), {
        ...options,
        ...customOptions
    });
};

describe('components/sw-import-export-entity-path-select', () => {
    it('should be a Vue.js component', () => {
        const swSingleSelect = createSingleSelect();

        expect(swSingleSelect.isVueInstance()).toBeTruthy();
    });
});
