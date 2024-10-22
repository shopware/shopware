/**
 * @package admin
 */

import { mount } from '@vue/test-utils';
import 'src/app/component/form/sw-select-rule-create';
import 'src/app/component/form/select/base/sw-single-select';
import 'src/app/component/form/select/entity/sw-entity-single-select';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/select/base/sw-select-result-list';
import 'src/app/component/form/select/base/sw-select-result';

describe('components/sw-select-rule-create', () => {
    const responses = global.repositoryFactoryMock.responses;

    responses.addResponse({
        method: 'Post',
        url: '/search/rule',
        status: 200,
        response: {
            data: [
                {
                    id: 'first-id',
                    attributes: {
                        id: 'first-id',
                        name: 'Always valid',
                        conditions: [false],
                    },
                    relationships: [],
                },
                {
                    id: 'second-id',
                    attributes: {
                        id: 'second-id',
                        name: 'Restricted rule',
                        conditions: [true],
                    },
                    relationships: [],
                },
            ],
        },
    });

    async function createWrapper() {
        return mount(await wrapTestComponent('sw-select-rule-create', { sync: true }), {
            global: {
                provide: {
                    ruleConditionDataProviderService: {
                        getRestrictedRules() {
                            return Promise.resolve(['second-id']);
                        },
                        getRestrictedRuleTooltipConfig: (ruleConditions) => {
                            if (ruleConditions.length < 1) {
                                return { disabled: true, message: '' };
                            }

                            return {
                                disabled: false,
                                message: 'ruleAwarenessRestrictionLabelText',
                            };
                        },
                        isRuleRestricted: (conditions) => {
                            return conditions[0];
                        },
                    },
                },
                stubs: {
                    'sw-entity-single-select': await wrapTestComponent('sw-entity-single-select'),
                    'sw-select-base': await wrapTestComponent('sw-select-base'),
                    'sw-block-field': await wrapTestComponent('sw-block-field'),
                    'sw-base-field': await wrapTestComponent('sw-base-field'),
                    'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
                    'sw-field-error': true,
                    'sw-icon': true,
                    'sw-loader': true,
                    'sw-highlight-text': {
                        props: ['text'],
                        template: '<div class="sw-highlight-text">{{ this.text }}</div>',
                    },
                    'sw-select-result': await wrapTestComponent('sw-select-result'),
                    'sw-popover': {
                        template: '<div class="sw-popover"><slot></slot></div>',
                    },
                    'sw-entity-multi-select': true,
                    'sw-rule-modal': true,
                    'sw-product-variant-info': true,
                    'sw-inheritance-switch': true,
                    'sw-ai-copilot-badge': true,
                    'sw-help-text': true,
                },
            },
            props: {
                ruleId: 'random-rule-id',
                restrictedRuleIds: ['restrictedId'],
                restrictedRuleIdsTooltipLabel: 'myRestrictedLabelText',
            },
        });
    }

    it('should be a vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should disable restricted rules', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.find('.sw-select__selection').trigger('click');
        await flushPromises();

        const resultItems = wrapper.findAllComponents('.sw-select-result-list__item-list .sw-select-result');
        expect(resultItems).toHaveLength(2);

        const [
            firstResult,
            secondResult,
        ] = resultItems;

        expect(firstResult.attributes('class')).not.toContain('is--disabled');
        expect(secondResult.attributes('class')).toContain('is--disabled');
    });

    it('should have disabled tooltip because rule is not in restricted array and not in rule awareness', async () => {
        const wrapper = await createWrapper();
        const tooltipConfig = wrapper.vm.tooltipConfig({
            id: 'ruleId',
            conditions: [],
        });

        expect(tooltipConfig.disabled).toBeTruthy();
        expect(tooltipConfig.message).toBe('');
    });

    it('should have correct tooltip because rule is in restricted array', async () => {
        const wrapper = await createWrapper();
        const tooltipConfig = wrapper.vm.tooltipConfig({
            id: 'restrictedId',
            conditions: [],
        });

        expect(tooltipConfig.disabled).toBeFalsy();
        expect(tooltipConfig.message).toBe('myRestrictedLabelText');
    });

    it('should have correct tooltip because of restricted rule by rule awareness', async () => {
        const wrapper = await createWrapper();
        const tooltipConfig = wrapper.vm.tooltipConfig({
            id: 'someRuleAwarenessRestrictedId',
            conditions: [true],
        });

        expect(tooltipConfig.disabled).toBeFalsy();
        expect(tooltipConfig.message).toBe('ruleAwarenessRestrictionLabelText');
    });
});
