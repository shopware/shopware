import { shallowMount } from '@vue/test-utils';
import ConditionDataProviderService from 'src/app/service/rule-condition.service';
import 'src/app/component/rule/condition-type/sw-condition-order-tag';
import 'src/app/component/rule/sw-condition-base';

async function createWrapper(condition = {}) {
    condition.getEntityName = () => 'rule_condition';

    return shallowMount(await Shopware.Component.build('sw-condition-order-tag'), {
        stubs: {
            'sw-condition-type-select': true,
            'sw-condition-operator-select': true,
            'sw-entity-tag-select': true,
            'sw-context-button': true,
            'sw-context-menu-item': true,
            'sw-field-error': true,
        },
        provide: {
            conditionDataProviderService: new ConditionDataProviderService(),
            availableTypes: {},
            availableGroups: [],
            childAssociationField: {},
            repositoryFactory: {
                create: () => {
                    return {
                        search: () => {
                            return Promise.resolve({});
                        }
                    };
                }
            }
        },
        propsData: {
            condition
        }
    });
}

describe('components/rule/condition-type/sw-condition-order-tag', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should set tags', async () => {
        const wrapper = await createWrapper({
            value: {
                identifiers: ['foo', 'bar'],
            },
        });

        wrapper.vm.setTags({
            getIds: () => {
                return ['foo', 'bar', 'baz'];
            }
        });

        expect(wrapper.vm.identifiers).toEqual(['foo', 'bar', 'baz']);
    });
});
