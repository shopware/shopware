import { shallowMount } from '@vue/test-utils';
import 'src/app/component/base/sw-card';
import 'src/app/component/base/sw-modal';
import 'src/app/component/entity/sw-entity-listing';
import 'src/app/component/data-grid/sw-data-grid';
import 'src/app/component/form/select/entity/advanced-selection-entities/sw-advanced-selection-rule';
import 'src/app/component/form/select/entity/sw-entity-advanced-selection-modal-grid';
import 'src/app/component/form/select/entity/sw-entity-advanced-selection-modal';
import flushPromises from 'flush-promises';

const itemData = [
    {
        id: 'first-id',
        type: 'rule',
        attributes: {
            id: 'first-id',
            name: 'Always valid'
        },
        relationships: []
    },
    {
        id: 'second-id',
        type: 'rule',
        attributes: {
            id: 'second-id',
            name: 'Restricted rule'
        },
        relationships: []
    }
];

function createWrapper() {
    const responses = global.repositoryFactoryMock.responses;

    responses.addResponse({
        method: 'Post',
        url: '/search/rule',
        status: 200,
        response: {
            data: itemData,

            meta: {
                totalCountMode: 1,
                total: 6
            }

        }
    });


    return shallowMount(Shopware.Component.build('sw-advanced-selection-rule'), {
        stubs: {
            'sw-button': {
                template: '<div></div>'
            },
            'sw-ignore-class': {
                template: '<div></div>'
            },
            'sw-card': {
                template: `<div>
                    <slot name="grid"></slot>
                </div>`,
            },
            'sw-icon': true,
            'sw-empty-state': true,
            'sw-modal': Shopware.Component.build('sw-modal'),
            'sw-entity-listing': Shopware.Component.build('sw-entity-listing'),
            'sw-entity-advanced-selection-modal': Shopware.Component.build('sw-entity-advanced-selection-modal'),
            'sw-entity-advanced-selection-modal-grid': {
                template: `<div>
                    <div v-for="entity in items" :class="entity.id">{{ entity.name }}</div>
                </div>`,
                props: [
                    'items'
                ],
            },
        },
        provide: {
            ruleConditionDataProviderService: {
                getGroups() {
                    return [];
                },
                getConditions() {
                    return [];
                }
            },
            filterService: {
                getStoredCriteria: () => {
                    return Promise.resolve([]);
                },
                mergeWithStoredFilters: (storeKey, criteria) => criteria
            },
            searchRankingService: {
                getSearchFieldsByEntity() {
                    return Promise.resolve(null);
                },
                buildSearchQueriesForEntity: () => {
                    return null;
                },
            },
            filterFactory: {
                create: (entityName, filters) => {
                    return Object.entries(filters);
                }
            },
            shortcutService: {
                startEventListener: () => {},
                stopEventListener: () => {}
            },
        },
        propsData: {
            ruleAwareGroupKey: 'personaPromotions',
            restrictionSnippet: 'rules',
        }
    });
}

describe('src/app/component/entity/sw-entity-advanced-selection-modal-grid', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should have some items', async () => {
        const wrapper = createWrapper();
        await flushPromises();

        itemData.forEach((data) => {
            expect(wrapper.find(`.${data.id}`).exists()).toBeTruthy();
        });
    });
});
