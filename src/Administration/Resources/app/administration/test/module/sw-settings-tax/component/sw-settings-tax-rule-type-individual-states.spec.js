import { createLocalVue, mount } from '@vue/test-utils';
import 'src/module/sw-settings-tax/component/sw-settings-tax-rule-type-individual-states';
import 'src/app/component/form/select/entity/sw-entity-multi-select';
import 'src/app/component/form/select/base/sw-select-base';

const stubs = {
    'sw-entity-multi-select': Shopware.Component.build('sw-entity-multi-select'),
    'sw-select-base': Shopware.Component.build('sw-select-base'),
    'sw-block-field': true,
    'sw-select-selection-list': true,
    'sw-select-result-list': true,
    'sw-highlight-text': true,
    'sw-icon': true
};

function createWrapper(taxRule) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return mount(Shopware.Component.build('sw-settings-tax-rule-type-individual-states'), {
        localVue,
        stubs,

        propsData: {
            taxRule
        },

        provide: {
            repositoryFactory: {
                create: (entityName) => {
                    if (entityName !== 'country_state') {
                        throw new Error('expected entity name to be country_state');
                    }

                    return {
                        entityName: 'country_state',
                        route: '/country_state',
                        search: (criteria) => {
                            const states = criteria.ids.map((id) => {
                                return {
                                    id,
                                    name: `state ${id}`
                                };
                            });

                            return Promise.resolve(states);
                        }
                    };
                }
            }
        }
    });
}

describe('module/sw-settings-tax/component/sw-settings-tax-rule-type-individual-states', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = createWrapper({
            data: {
                states: []
            }
        });

        expect(wrapper.vm).toBeTruthy();
    });

    it('creates an empty entity collection if taxRule.data.states is empty', async () => {
        const wrapper = createWrapper({
            data: {
                states: []
            }
        });

        const individualStates = wrapper.vm.individualStates;

        expect(individualStates).toBeInstanceOf(Array);
        expect(individualStates).toHaveLength(0);
        expect(individualStates.entity).toBe('country_state');
        expect(individualStates.source).toBe('/country_state');

        wrapper.destroy();
    });

    it('fetches country states at creation ', async () => {
        const states = [
            Shopware.Utils.createId(),
            Shopware.Utils.createId()
        ];

        const wrapper = await createWrapper({
            data: {
                states
            }
        });

        const individualStates = wrapper.vm.individualStates;
        expect(individualStates).toHaveLength(2);
        expect(individualStates).toEqual(expect.arrayContaining([{
            id: states[0],
            name: `state ${states[0]}`
        }, {
            id: states[1],
            name: `state ${states[1]}`
        }]));

        wrapper.destroy();
    });

    it('only updates its states if multiselect emits a change', () => {
        const states = [
            { id: Shopware.Utils.createId() },
            { id: Shopware.Utils.createId() }
        ];

        const wrapper = createWrapper({
            countryId: Shopware.Utils.createId(),
            data: {
                states: []
            }
        });

        const select = wrapper.findComponent(stubs['sw-entity-multi-select']);

        select.vm.$emit('change', new Shopware.Data.EntityCollection(
            '/country-state',
            'country-state',
            Shopware.Context.api,
            new Shopware.Data.Criteria(),
            states
        ));

        expect(wrapper.vm.individualStates).toEqual(expect.arrayContaining(states));

        wrapper.destroy();
    });
});
