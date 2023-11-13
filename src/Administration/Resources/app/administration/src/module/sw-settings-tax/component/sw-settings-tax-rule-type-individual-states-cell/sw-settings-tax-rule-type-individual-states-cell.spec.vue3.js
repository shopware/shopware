import { mount } from '@vue/test-utils_v3';

/**
 * @package customer-order
 */
async function createWrapper(taxRule) {
    taxRule.type = { typeName: 'Individual States' };

    return mount(await wrapTestComponent('sw-settings-tax-rule-type-individual-states-cell', {
        sync: true,
    }), {
        props: {
            taxRule,
        },

        global: {
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
                                        name: `state ${id}`,
                                    };
                                });

                                return Promise.resolve(states);
                            },
                        };
                    },
                },
            },
        },
    });
}

describe('module/sw-settings-tax/component/sw-settings-tax-rule-type-individual-states', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper({
            data: {
                states: [],
            },
        });

        expect(wrapper.vm).toBeTruthy();
    });

    it('creates an empty array taxRule.data.states is empty', async () => {
        const wrapper = await createWrapper({
            data: {
                states: [],
            },
        });

        const individualStates = wrapper.vm.individualStates;

        expect(individualStates).toBeInstanceOf(Array);
        expect(individualStates).toHaveLength(0);
    });

    it('fetches country states at creation', async () => {
        const states = [
            Shopware.Utils.createId(),
            Shopware.Utils.createId(),
        ];

        const wrapper = await createWrapper({
            data: {
                states,
            },
        });

        const individualStates = wrapper.vm.individualStates;
        expect(individualStates).toHaveLength(2);
        expect(individualStates).toEqual(expect.arrayContaining([
            `state ${states[0]}`,
            `state ${states[1]}`,
        ]));
    });

    it('watches for changes in its props', async () => {
        const wrapper = await createWrapper({
            data: {
                states: [],
            },
        });
        expect(wrapper.vm.individualStates).toBeInstanceOf(Array);
        expect(wrapper.vm.individualStates).toHaveLength(0);

        const stateId = Shopware.Utils.createId();

        await wrapper.setProps({ taxRule: {
            type: { typeName: 'Individual States' },
            data: {
                states: [stateId],
            },
        } });

        expect(wrapper.vm.individualStates).toEqual(expect.arrayContaining([
            `state ${stateId}`,
        ]));
    });
});
