/**
 * @package buyers-experience
 */
import { createLocalVue, shallowMount } from '@vue/test-utils';
import swSettingsSearchSearchBehaviour from 'src/module/sw-settings-search/component/sw-settings-search-search-behaviour';
import 'src/app/component/form/sw-radio-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/sw-number-field';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/field-base/sw-block-field';

Shopware.Component.register('sw-settings-search-search-behaviour', swSettingsSearchSearchBehaviour);

async function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(await Shopware.Component.build('sw-settings-search-search-behaviour'), {
        localVue,

        propsData: {
            searchBehaviourConfigs: {
                andLogic: true,
                minSearchLength: 2,
            },
        },

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
            'sw-radio-field': await Shopware.Component.build('sw-radio-field'),
            'sw-base-field': await Shopware.Component.build('sw-base-field'),
            'sw-field-error': true,
            'sw-number-field': await Shopware.Component.build('sw-number-field'),
            'sw-text-field': await Shopware.Component.build('sw-text-field'),
            'sw-contextual-field': await Shopware.Component.build('sw-contextual-field'),
            'sw-block-field': await Shopware.Component.build('sw-block-field'),
        },

        attachTo: document.body,
    });
}

describe('module/sw-settings-search/component/sw-settings-search-search-behaviour', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should not be able to change the behaviour search which includes and, or', async () => {
        const wrapper = await createWrapper([
            'product_search_config.viewer',
        ]);
        await wrapper.vm.$nextTick();

        const andBehaviourElement = wrapper.find('.sw-settings-search__search-behaviour-condition').findAll('input').at(0);
        expect(andBehaviourElement.attributes().disabled).toBeTruthy();

        const orBehaviourElement = wrapper.find('.sw-settings-search__search-behaviour-condition').findAll('input').at(1);
        expect(orBehaviourElement.attributes().disabled).toBeTruthy();

        const minSearchLengthElement = wrapper.find('.sw-settings-search__search-behaviour-term-length input');
        expect(minSearchLengthElement.attributes().disabled).toBeTruthy();

        await orBehaviourElement.trigger('click');
        expect(orBehaviourElement.element.checked).toBeFalsy();
        expect(wrapper.vm.searchBehaviourConfigs.andLogic).toBe(true);
    });

    it('should be able to change the behaviour search which includes and, or', async () => {
        const wrapper = await createWrapper([
            'product_search_config.editor',
        ]);
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.searchBehaviourConfigs.andLogic).toBe(true);

        const orBehaviourElement = wrapper.find('.sw-settings-search__search-behaviour-condition').findAll('input').at(1);
        await orBehaviourElement.trigger('click');
        expect(orBehaviourElement.element.checked).toBeTruthy();
        expect(wrapper.vm.searchBehaviourConfigs.andLogic).toBe(false);

        const andBehaviourElement = wrapper.find('.sw-settings-search__search-behaviour-condition').findAll('input').at(0);
        await andBehaviourElement.trigger('click');
        expect(andBehaviourElement.element.checked).toBeTruthy();
        expect(wrapper.vm.searchBehaviourConfigs.andLogic).toBe(true);
    });

    it('should be able to change minimal search term length between limit value', async () => {
        const wrapper = await createWrapper([
            'product_search_config.editor',
        ]);
        await wrapper.vm.$nextTick();

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
