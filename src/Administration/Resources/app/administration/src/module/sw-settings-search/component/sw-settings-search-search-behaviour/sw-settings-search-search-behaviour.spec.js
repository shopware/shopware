/**
 * @sw-package inventory
 */
import { mount } from '@vue/test-utils';

async function createWrapper(privileges = []) {
    return mount(
        await wrapTestComponent('sw-settings-search-search-behaviour', {
            sync: true,
        }),
        {
            props: {
                searchBehaviourConfigs: {
                    strictness: 100,
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
                    'sw-radio-field': await wrapTestComponent('sw-radio-field'),
                    'sw-base-field': await wrapTestComponent('sw-base-field'),
                    'sw-field-error': true,
                    'sw-text-field': await wrapTestComponent('sw-text-field'),
                    'sw-text-field-deprecated': await wrapTestComponent('sw-text-field-deprecated', { sync: true }),
                    'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                    'sw-block-field': await wrapTestComponent('sw-block-field'),
                    'sw-help-text': true,
                    'sw-field-copyable': true,
                    'sw-inheritance-switch': true,
                    'sw-ai-copilot-badge': true,
                },

                attachTo: document.body,
            },
        },
    );
}

describe('module/sw-settings-search/component/sw-settings-search-search-behaviour', () => {
    it('should not be able to change the search strictness without edit permissions', async () => {
        const wrapper = await createWrapper([
            'product_search_config.viewer',
        ]);
        await flushPromises();
        const strictnessElements = wrapper.find('.sw-settings-search__search-behaviour-condition').findAll('input');

        expect(strictnessElements).toHaveLength(5);
        strictnessElements.forEach((element) => {
            expect(element.attributes().disabled).toBeDefined();
        });

        const minSearchLengthElement = wrapper.findByLabel('sw-settings-search.generalTab.labelMinimalSearchTerm');
        expect(minSearchLengthElement.attributes().disabled).toBeDefined();

        await strictnessElements.at(2).trigger('click');
        expect(strictnessElements.at(2).element.checked).toBeFalsy();
        expect(wrapper.vm.searchBehaviourConfigs.strictness).toBe(100);
    });

    it('should expose the strictness presets for merchants', async () => {
        const wrapper = await createWrapper([
            'product_search_config.editor',
        ]);

        expect(wrapper.vm.strictnessOptions).toEqual([
            expect.objectContaining({ value: 0 }),
            expect.objectContaining({ value: 33 }),
            expect.objectContaining({ value: 50 }),
            expect.objectContaining({ value: 66 }),
            expect.objectContaining({ value: 100 }),
        ]);
    });

    it('should be able to change minimal search term length between limit value', async () => {
        const wrapper = await createWrapper([
            'product_search_config.editor',
        ]);
        await flushPromises();

        expect(wrapper.vm.searchBehaviourConfigs.minSearchLength).toBe(2);

        const minSearchLengthElement = wrapper.findByLabel('sw-settings-search.generalTab.labelMinimalSearchTerm');
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
