import { shallowMount } from '@vue/test-utils';
import swPriceRuleModal from 'src/module/sw-settings-shipping/component/sw-price-rule-modal';
import 'src/app/component/rule/sw-rule-modal';

/**
 * @package checkout
 */

Shopware.Component.extend('sw-price-rule-modal', 'sw-rule-modal', swPriceRuleModal);

function createRuleMock(isNew) {
    return {
        id: '1',
        name: 'Test rule',
        isNew: () => isNew,
        conditions: [{
            entity: 'rule',
            source: 'foo/rule',
            children: [{
                id: 'some-id',
                children: [{
                    id: 'some-id'
                }]
            }]
        }],
        someRuleRelation: []
    };
}

async function createWrapper() {
    return shallowMount(await Shopware.Component.build('sw-price-rule-modal'), {
        provide: {
            repositoryFactory: {
                create: () => {
                    return {
                        create: () => {
                            return createRuleMock(true);
                        },
                        get: () => Promise.resolve(createRuleMock(false)),
                        save: () => Promise.resolve(),
                        search: () => Promise.resolve([])
                    };
                }
            },

            ruleConditionDataProviderService: {
                getModuleTypes: () => [],
                addScriptConditions: () => {},
                getRestrictedRuleTooltipConfig: () => ({
                    disabled: true
                })
            },

            ruleConditionsConfigApiService: {
                load: () => Promise.resolve()
            }
        },

        stubs: {
            'sw-modal': {
                template: `
                    <div class="sw-modal">
                      <slot name="modal-header"></slot>
                      <slot></slot>
                      <slot name="modal-footer"></slot>
                    </div>
                `
            },
            'sw-button': {
                template: '<button @click="$emit(\'click\', $event)"><slot></slot></button>'
            },
            'sw-condition-tree': true,
            'sw-container': true,
            'sw-multi-select': true,
            'sw-textarea-field': true,
            'sw-number-field': true,
            'sw-text-field': true,
            'sw-field': true
        }
    });
}

describe('module/sw-settings-shipping/component/sw-price-rule-modal', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });
});

