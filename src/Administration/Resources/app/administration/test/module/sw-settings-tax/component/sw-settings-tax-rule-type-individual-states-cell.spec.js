import { createLocalVue, mount } from '@vue/test-utils';
import 'src/module/sw-settings-tax/component/sw-settings-tax-rule-type-individual-states-cell';

function createWrapper(taxRule) {
    taxRule.type = { typeName: 'Individual States' };

    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return mount(Shopware.Component.build('sw-settings-tax-rule-type-individual-states-cell'), {
        localVue,

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

    it('creates an empty array taxRule.data.states is empty', async () => {
        const wrapper = createWrapper({
            data: {
                states: []
            }
        });

        const individualStates = wrapper.vm.individualStates;

        expect(individualStates).toBeInstanceOf(Array);
        expect(individualStates).toHaveLength(0);

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
        expect(individualStates).toEqual(expect.arrayContaining([
            `state ${states[0]}`,
            `state ${states[1]}`
        ]));

        wrapper.destroy();
    });

    it('watches for changes in its props', async () => {
        const wrapper = createWrapper({
            data: {
                states: []
            }
        });
        expect(wrapper.vm.individualStates).toBeInstanceOf(Array);
        expect(wrapper.vm.individualStates).toHaveLength(0);

        const stateId = Shopware.Utils.createId();

        await wrapper.setProps({ taxRule: {
            type: { typeName: 'Individual States' },
            data: {
                states: [stateId]
            }
        } });

        expect(wrapper.vm.individualStates).toEqual(expect.arrayContaining([
            `state ${stateId}`
        ]));

        wrapper.destroy();
    });
});
