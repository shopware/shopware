/**
 * @package system-settings
 */
import { mount } from '@vue/test-utils';

async function createWrapper(privileges = []) {
    return mount(await wrapTestComponent('sw-settings-search-search-behaviour', {
        sync: true,
    }), {
        props: {
            searchBehaviourConfigs: {
                andLogic: true,
                minSearchLength: 2,
            },
        },

        global: {
            renderStubDefaultSlot: true,
            mocks: {
                $route: {
                    query: {
                        page: 1,
                        limit: 25,
                    },
                },
            },

            provide: {
                validationService: {},
                acl: {
                    can: (identifier) => {
                        if (!identifier) {
                            return true;
                        }

                        return privileges.includes(identifier);
                    },
                },

            },

            stubs: {
                'sw-card': true,
                'sw-radio-field': await wrapTestComponent('sw-radio-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-field-error': true,
                'sw-number-field': await wrapTestComponent('sw-number-field'),
                'sw-number-field-deprecated': await wrapTestComponent('sw-number-field-deprecated', { sync: true }),
                'sw-text-field': await wrapTestComponent('sw-text-field'),
                'sw-text-field-deprecated': await wrapTestComponent('sw-text-field-deprecated', { sync: true }),
                'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
            },

            attachTo: document.body,
        },

    });
}

describe('module/sw-settings-search/component/sw-settings-search-search-behaviour', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should not be able to change the behaviour search which includes and, or', async () => {
        const wrapper = await createWrapper([
            'product_search_config.viewer',
        ]);
        await flushPromises();

        const andBehaviourElement = wrapper.find('.sw-settings-search__search-behaviour-condition').findAll('input').at(0);
        expect(andBehaviourElement.attributes().disabled).toBeDefined();

        const orBehaviourElement = wrapper.find('.sw-settings-search__search-behaviour-condition').findAll('input').at(1);
        expect(orBehaviourElement.attributes().disabled).toBeDefined();

        const minSearchLengthElement = wrapper.find('.sw-settings-search__search-behaviour-term-length input');
        expect(minSearchLengthElement.attributes().disabled).toBeDefined();

        await orBehaviourElement.trigger('click');
        expect(orBehaviourElement.element.checked).toBeFalsy();
        expect(wrapper.vm.searchBehaviourConfigs.andLogic).toBe(true);
    });

    it('should be able to change minimal search term length between limit value', async () => {
        const wrapper = await createWrapper([
            'product_search_config.editor',
        ]);
        await flushPromises();

        expect(wrapper.vm.searchBehaviourConfigs.minSearchLength).toBe(2);

        const minSearchLengthElement = wrapper.find('.sw-settings-search__search-behaviour-term-length input');
        await minSearchLengthElement.setValue(3);
        await minSearchLengthElement.trigger('change');
        expect(wrapper.vm.searchBehaviourConfigs.minSearchLength).toBe(3);

        // take the max value if the current value bigger than the max value.
        await minSearchLengthElement.setValue(21);
        await minSearchLengthElement.trigger('change');
        expect(wrapper.vm.searchBehaviourConfigs.minSearchLength).toBe(20);

        // take the min value if the current value smaller than the min value.
        await minSearchLengthElement.setValue(0);
        await minSearchLengthElement.trigger('change');
        expect(wrapper.vm.searchBehaviourConfigs.minSearchLength).toBe(1);
    });
});
