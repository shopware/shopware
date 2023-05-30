import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/app/component/form/select/entity/sw-entity-advanced-selection-modal';
import 'src/app/component/form/select/entity/advanced-selection-entities/sw-advanced-selection-rule';
import 'src/app/component/base/sw-modal';
import 'src/app/component/base/sw-card';
import 'src/app/component/data-grid/sw-data-grid';
import 'src/app/component/data-grid/sw-data-grid-settings';
import 'src/app/component/entity/sw-entity-listing';
import 'src/app/component/context-menu/sw-context-button';
import 'src/app/component/context-menu/sw-context-menu-item';
import 'src/app/component/base/sw-button';
import 'src/app/component/grid/sw-pagination';
import 'src/app/component/base/sw-empty-state';
import 'src/app/component/structure/sw-page';
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
    const localVue = createLocalVue();

    return {
        wrapper: shallowMount(await Shopware.Component.build('sw-advanced-selection-rule'), {
            localVue,
            provide: {
                ruleConditionDataProviderService: {
                    getGroups: () => {
                        return [];
                    },
                    getConditions: () => {
                        return [];
                    },
                    getRestrictedRuleTooltipConfig: () => {
                        return { disabled: false, message: 'ruleAwarenessRestrictionLabelText' };
                    },
                    isRuleRestricted: (conditions) => { return conditions[0]; },
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
            propsData: {
                ruleAwareGroupKey: 'item',
                restrictedRuleIds: ['1'],
                restrictedRuleIdsTooltipLabel: 'restricted',
            },
            stubs: {
                'sw-entity-advanced-selection-modal': await Shopware.Component.build('sw-entity-advanced-selection-modal'),
                'sw-entity-listing': await Shopware.Component.build('sw-entity-listing'),
                'sw-modal': await Shopware.Component.build('sw-modal'),
                'sw-card': await Shopware.Component.build('sw-card'),
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
            },
        }),
    };
}

describe('components/sw-advanced-selection-product', () => {
    let wrapper;
    let selectionModal;

    beforeEach(async () => {
        const data = await createWrapper();
        wrapper = data.wrapper;
        selectionModal = wrapper.findComponent({ name: 'sw-entity-advanced-selection-modal' });
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.JS component that wraps the selection modal component', async () => {
        expect(wrapper.vm).toBeTruthy();
        expect(selectionModal.exists()).toBe(true);
        expect(selectionModal.vm).toBeTruthy();
    });

    it('should get disabled column class', async () => {
        const cls = wrapper.vm.getColumnClass({ id: '1', conditions: [true] });

        expect(cls).toBe('sw-advanced-selection-rule-disabled');
    });

    it('should get restricted tooltip', async () => {
        let tooltip = wrapper.vm.tooltipConfig({ id: '1' });

        expect(tooltip.message).toBe('restricted');

        tooltip = wrapper.vm.tooltipConfig({ id: '2' });

        expect(tooltip.message).toBe('ruleAwarenessRestrictionLabelText');
    });

    it('should notice if record selectable', async () => {
        const obj = wrapper.vm.isRecordSelectable({ id: '1', conditions: [true] });

        expect(obj.isSelectable).toBeFalsy();
        expect(obj.tooltip.message).toBe('restricted');
    });

    it('should return counts', async () => {
        const aggregations = {
            productPrices: {
                buckets: [{
                    key: '1',
                    productPrices: {
                        count: 100,
                    },
                }],
            },
        };

        const counts = wrapper.vm.getCounts('1', aggregations);

        expect(counts.productPrices).toBe(100);
    });
});
