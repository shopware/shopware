/**
 * @package admin
 */

import { shallowMount } from '@vue/test-utils';
import 'src/app/component/form/sw-select-rule-create';
import 'src/app/component/form/select/base/sw-single-select';
import 'src/app/component/form/select/entity/sw-entity-single-select';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/select/base/sw-select-result-list';
import 'src/app/component/form/select/base/sw-select-result';

describe('components/sw-select-rule-create', () => {
    let wrapper;

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
                        conditions: [false]
                    },
                    relationships: []
                },
                {
                    id: 'second-id',
                    attributes: {
                        id: 'second-id',
                        name: 'Restricted rule',
                        conditions: [true]
                    },
                    relationships: []
                }
            ]
        }
    });

    async function createWrapper() {
        return shallowMount(await Shopware.Component.build('sw-select-rule-create'), {
            provide: {
                ruleConditionDataProviderService: {
                    getRestrictedRules() {
                        return Promise.resolve(['second-id']);
                    },
                    getRestrictedRuleTooltipConfig: (ruleConditions) => {
                        if (ruleConditions.length < 1) {
                            return { disabled: true, message: '' };
                        }

                        return { disabled: false, message: 'ruleAwarenessRestrictionLabelText' };
                    },
                    isRuleRestricted: (conditions) => {
                        return conditions[0];
                    },
                },
            },
            stubs: {
                'sw-entity-single-select': await Shopware.Component.build('sw-entity-single-select'),
                'sw-select-base': await Shopware.Component.build('sw-select-base'),
                'sw-block-field': await Shopware.Component.build('sw-block-field'),
                'sw-base-field': await Shopware.Component.build('sw-base-field'),
                'sw-select-result-list': await Shopware.Component.build('sw-select-result-list'),
                'sw-field-error': true,
                'sw-icon': true,
                'sw-loader': true,
                'sw-highlight-text': {
                    props: ['text'],
                    template: '<div class="sw-highlight-text">{{ this.text }}</div>'
                },
                'sw-select-result': await Shopware.Component.build('sw-select-result'),
                'sw-popover': {
                    template: '<div class="sw-popover"><slot></slot></div>'
                },
            },
            propsData: {
                ruleId: 'random-rule-id',
                restrictedRuleIds: ['restrictedId'],
                restrictedRuleIdsTooltipLabel: 'myRestrictedLabelText'
            }
        });
    }

    afterEach(() => {
        if (wrapper) {
            wrapper.destroy();
            wrapper = null;
        }
    });

    it('should be a vue.js component', async () => {
        wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should disable restricted rules', async () => {
        wrapper = await createWrapper();

        await wrapper.find('.sw-select__selection').trigger('click');

        await flushPromises();

        const resultItems = wrapper.findAll('.sw-select-result-list__item-list .sw-select-result');
        expect(resultItems.wrappers.length).toBe(2);

        const [firstResult, secondResult] = resultItems.wrappers;

        expect(firstResult.attributes('class')).not.toContain('is--disabled');
        expect(secondResult.attributes('class')).toContain('is--disabled');
    });

    it('should have disabled tooltip because rule is not in restricted array and not in rule awareness', async () => {
        wrapper = await createWrapper();
        const tooltipConfig = wrapper.vm.tooltipConfig({ id: 'ruleId', conditions: [] });

        expect(tooltipConfig.disabled).toBeTruthy();
        expect(tooltipConfig.message).toEqual('');
    });

    it('should have correct tooltip because rule is in restricted array', async () => {
        wrapper = await createWrapper();
        const tooltipConfig = wrapper.vm.tooltipConfig({ id: 'restrictedId', conditions: [] });

        expect(tooltipConfig.disabled).toBeFalsy();
        expect(tooltipConfig.message).toEqual('myRestrictedLabelText');
    });

    it('should have correct tooltip because of restricted rule by rule awareness', async () => {
        wrapper = await createWrapper();
        const tooltipConfig = wrapper.vm.tooltipConfig({ id: 'someRuleAwarenessRestrictedId', conditions: [true] });

        expect(tooltipConfig.disabled).toBeFalsy();
        expect(tooltipConfig.message).toEqual('ruleAwarenessRestrictionLabelText');
    });
});
