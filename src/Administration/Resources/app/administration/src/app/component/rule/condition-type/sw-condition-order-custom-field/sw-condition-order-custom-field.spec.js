import { shallowMount } from '@vue/test-utils';
import 'src/app/component/rule/sw-condition-base';
import 'src/app/component/rule/condition-type/sw-condition-order-custom-field';
import ConditionDataProviderService from 'src/app/service/rule-condition.service';

async function createWrapper(condition) {
    return shallowMount(await Shopware.Component.build('sw-condition-order-custom-field'), {
        provide: {
            repositoryFactory: {
                create: () => ({
                    search: () => ({
                        get: () => ({
                            id: 'id',
                            name: 'name',
                            customFields: [],
                            relations: {
                                entityName: 'order',
                            },
                        }),
                    }),
                }),
            },
            feature: {
                isActive: () => true,
            },
            conditionDataProviderService: new ConditionDataProviderService(),
            availableTypes: [],
            availableGroups: [],
            childAssociationField: {},
            // ruleConditionDataProviderService: {
            //     getModuleTypes: () => [],
            //     addScriptConditions: () => {},
            //     getRestrictedRuleTooltipConfig: () => ({
            //         disabled: true
            //     })
            // },
            //
            // ruleConditionsConfigApiService: {
            //     load: () => Promise.resolve()
            // }
        },

        propsData: {
            condition
            // sequence: {},
            // ruleAwareGroupKey: 'someRuleRelation',
        },

        stubs: {
            'sw-condition-tree-node': true,
            'sw-single-select': true,
            'sw-condition-type-select': true,
            'sw-entity-single-select': {
                template: '<div class="sw-entity-single-select"></div>'
            },
            'sw-context-button': {
                template: '<div class="sw-context-button"><slot></slot></div>'
            },
            'sw-context-menu-item': {
                template: '<div class="sw-context-menu-item"><slot></slot></div>'
            },
            'sw-field-error': true,
            // 'sw-modal': {
            //     template: `
            //         <div class="sw-modal">
            //           <slot name="modal-header"></slot>
            //           <slot></slot>
            //           <slot name="modal-footer"></slot>
            //         </div>
            //     `
            // },
            // 'sw-button': {
            //     template: '<button @click="$emit(\'click\', $event)"><slot></slot></button>'
            // },
            // 'sw-button-process': {
            //     template: '<button @click="$emit(\'click\', $event)"><slot></slot></button>'
            // },
            // 'sw-condition-base': true,
            // 'sw-icon': true,
            // 'sw-condition-tree': true,
            // 'sw-container': true,
            // 'sw-multi-select': true,
            // 'sw-textarea-field': true,
            // 'sw-number-field': true,
            // 'sw-text-field': true,
            // 'sw-field': true
        },

        mocks: {
            $tc: (value) => value,
        },
    });
}

describe('src/app/component/rule/condition-type/sw-condition-order-custom-field', () => {
    const condition = {
        type: 'orderCustomField',
        value: {
            renderedField: null,
            selectedField: null,
            selectedFieldSet: null,
            operator: null,
            renderedFieldValue: null,
        },
    };


    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper(condition);
        expect(wrapper.vm).toBeTruthy();
    });

    it('should have a template', async () => {
        const wrapper = await createWrapper(condition);
        expect(wrapper.vm.$options.template).toBeDefined();
    });
});
