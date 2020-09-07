import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-settings-tax/component/sw-settings-tax-rule-type-individual-states';
import 'src/app/component/form/select/entity/sw-entity-multi-select';
import 'src/app/component/form/select/base/sw-select-base';

function createWrapper() {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-settings-tax-rule-type-individual-states'), {
        localVue,

        propsData: {
            taxRule: {
                data: {
                    states: []
                }
            }
        },

        mocks: {
            $tc: key => key
        },

        provide: {
            repositoryFactory: {
                create: () => ({
                    search: () => {
                        return Promise.resolve([]);
                    }
                })
            }
        },

        stubs: {
            'sw-entity-multi-select': Shopware.Component.build('sw-entity-multi-select'),
            'sw-select-base': Shopware.Component.build('sw-select-base'),
            'sw-select-selection-list': true,
            'sw-select-result-list': true,
            'sw-highlight-text': true
        }
    });
}

describe('module/sw-settings-tax/component/sw-settings-tax-rule-type-individual-states', () => {
    it('should be a Vue.JS component', () => {
        const wrapper = createWrapper();

        expect(wrapper.isVueInstance()).toBe(true);
    });

    it('xxx', async () => {
        const wrapper = createWrapper();

        expect(wrapper.exists()).toBe(true);
    });
});
