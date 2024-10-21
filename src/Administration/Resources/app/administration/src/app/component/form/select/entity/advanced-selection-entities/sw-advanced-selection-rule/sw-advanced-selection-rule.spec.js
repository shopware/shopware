/**
 * @package admin
 */
import { mount } from '@vue/test-utils';

import { searchRankingPoint } from 'src/app/service/search-ranking.service';

const responses = global.repositoryFactoryMock.responses;

responses.addResponse({
    method: 'Post',
    url: '/search/rule',
    status: 200,
    response: {
        data: [
            {
                id: '1',
                attributes: {
                    id: '1',
                },
            },
        ],
    },
});

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-advanced-selection-rule', {
            sync: true,
        }),
        {
            props: {
                ruleAwareGroupKey: 'item',
                restrictedRuleIds: ['1'],
                restrictedRuleIdsTooltipLabel: 'restricted',
            },
            global: {
                stubs: {
                    'sw-entity-advanced-selection-modal': await wrapTestComponent('sw-entity-advanced-selection-modal'),
                    'sw-entity-listing': await wrapTestComponent('sw-entity-listing'),
                    'sw-modal': await wrapTestComponent('sw-modal'),
                    'sw-card': await wrapTestComponent('sw-card'),
                    'sw-card-deprecated': await wrapTestComponent('sw-card-deprecated', { sync: true }),
                    'sw-context-button': {
                        template: '<div></div>',
                    },
                    'sw-icon': {
                        template: '<div></div>',
                    },
                    'router-link': true,
                    'sw-button': {
                        template: '<div></div>',
                    },
                    'sw-checkbox-field': {
                        template: '<div></div>',
                    },
                    'sw-ignore-class': {
                        template: '<div></div>',
                    },
                    'sw-loader': true,
                    'sw-label': true,
                    'sw-filter-panel': true,
                    'sw-context-menu': true,
                    'sw-card-filter': true,
                    'sw-entity-advanced-selection-modal-grid': true,
                    'sw-empty-state': true,
                    'mt-card': true,
                    'sw-extension-component-section': true,
                    'sw-ai-copilot-badge': true,
                },
                provide: {
                    ruleConditionDataProviderService: {
                        getGroups: () => {
                            return [];
                        },
                        getConditions: () => {
                            return [];
                        },
                        getRestrictedRuleTooltipConfig: () => {
                            return {
                                disabled: false,
                                message: 'ruleAwarenessRestrictionLabelText',
                            };
                        },
                        isRuleRestricted: (conditions) => {
                            return conditions[0];
                        },
                    },
                    filterFactory: {
                        create: () => [],
                    },
                    filterService: {
                        getStoredCriteria: () => {
                            return Promise.resolve([]);
                        },
                        mergeWithStoredFilters: (storeKey, criteria) => criteria,
                    },
                    shortcutService: {
                        startEventListener() {},
                        stopEventListener() {},
                    },
                    searchRankingService: {
                        getSearchFieldsByEntity: () => {
                            return Promise.resolve({
                                name: searchRankingPoint.HIGH_SEARCH_RANKING,
                            });
                        },
                        buildSearchQueriesForEntity: (searchFields, term, criteria) => {
                            return criteria;
                        },
                    },
                },
            },
        },
    );
}

describe('components/sw-advanced-selection-rule', () => {
    it('should be a Vue.JS component that wraps the selection modal component', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm).toBeTruthy();

        const selectionModal = document.body.querySelector('.sw-modal');
        expect(selectionModal).toBeInTheDocument();
    });

    it('should get disabled column class', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const cls = wrapper.vm.getColumnClass({ id: '1', conditions: [true] });

        expect(cls).toBe('sw-advanced-selection-rule-disabled');
    });

    it('should get restricted tooltip', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        let tooltip = wrapper.vm.tooltipConfig({ id: '1' });

        expect(tooltip.message).toBe('restricted');

        tooltip = wrapper.vm.tooltipConfig({ id: '2' });

        expect(tooltip.message).toBe('ruleAwarenessRestrictionLabelText');
    });

    it('should notice if record selectable', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const obj = wrapper.vm.isRecordSelectable({
            id: '1',
            conditions: [true],
        });

        expect(obj.isSelectable).toBeFalsy();
        expect(obj.tooltip.message).toBe('restricted');
    });

    it('should return counts', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const aggregations = {
            productPrices: {
                buckets: [
                    {
                        key: '1',
                        productPrices: {
                            count: 100,
                        },
                    },
                ],
            },
        };

        const counts = wrapper.vm.getCounts('1', aggregations);

        expect(counts.productPrices).toBe(100);
    });

    it('should return filters from filter registry', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.dateFilter).toEqual(expect.any(Function));
    });
});
