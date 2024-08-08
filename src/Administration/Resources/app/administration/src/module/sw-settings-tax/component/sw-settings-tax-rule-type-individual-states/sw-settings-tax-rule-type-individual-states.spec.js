import { mount } from '@vue/test-utils';

/**
 * @package customer-order
 * @group disabledCompat
 */
describe('module/sw-settings-tax/component/sw-settings-tax-rule-type-individual-states', () => {
    async function createWrapper(taxRule) {
        return mount(await wrapTestComponent('sw-settings-tax-rule-type-individual-states', {
            sync: true,
        }), {
            props: {
                taxRule,
            },

            global: {
                renderStubDefaultSlot: true,

                stubs: {
                    'sw-entity-multi-select': await wrapTestComponent('sw-entity-multi-select', {
                        sync: true,
                    }),
                    'sw-select-base': await wrapTestComponent('sw-select-base', {
                        sync: true,
                    }),
                    'sw-block-field': await wrapTestComponent('sw-block-field', {
                        sync: true,
                    }),
                    'sw-base-field': await wrapTestComponent('sw-base-field', {
                        sync: true,
                    }),
                    'sw-select-selection-list': true,
                    'sw-select-result-list': true,
                    'sw-highlight-text': true,
                    'sw-icon': true,
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

    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper({
            data: {
                states: [],
            },
        });

        expect(wrapper.vm).toBeTruthy();
    });

    it('creates an empty entity collection if taxRule.data.states is empty', async () => {
        const wrapper = await createWrapper({
            data: {
                states: [],
            },
        });

        const individualStates = wrapper.vm.individualStates;

        expect(individualStates).toBeInstanceOf(Array);
        expect(individualStates).toHaveLength(0);
        expect(individualStates.entity).toBe('country_state');
        expect(individualStates.source).toBe('/country_state');
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
        expect(individualStates).toEqual(expect.arrayContaining([{
            id: states[0],
            name: `state ${states[0]}`,
        }, {
            id: states[1],
            name: `state ${states[1]}`,
        }]));
    });
});
