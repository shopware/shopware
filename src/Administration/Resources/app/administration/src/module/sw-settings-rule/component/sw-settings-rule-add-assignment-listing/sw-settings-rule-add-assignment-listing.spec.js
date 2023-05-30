/* eslint-disable max-len */
import { shallowMount, createLocalVue } from '@vue/test-utils';
import swSettingsRuleAddAssignmentListing from 'src/module/sw-settings-rule/component/sw-settings-rule-add-assignment-listing';
import 'src/app/component/data-grid/sw-data-grid';
import EntityCollection from 'src/core/data/entity-collection.data';

Shopware.Component.register('sw-settings-rule-add-assignment-listing', swSettingsRuleAddAssignmentListing);

function createEntityCollectionMock(entityName, items = []) {
    return new EntityCollection('/route', entityName, {}, {}, items, items.length);
}

async function createWrapper(entityContext) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(await Shopware.Component.build('sw-settings-rule-add-assignment-listing'), {
        localVue,
        stubs: {
            'sw-card': {
                template: '<div class="sw-card"><slot></slot></div>',
            },
            'sw-data-grid': await Shopware.Component.build('sw-data-grid'),
            'sw-pagination': true,
            'sw-data-grid-skeleton': true,
            'sw-checkbox-field': true,
            'sw-icon': true,
            'sw-button': true,
        },
        propsData: {
            ruleId: 'uuid1',
            entityContext: entityContext,
        },
        provide: {
        },
    });
}

describe('src/module/sw-settings-rule/view/sw-settings-rule-add-assignment-listing', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper({
            id: 'event_action',
            notAssignedDataTotal: 0,
            allowAdd: true,
            entityName: 'event_action',
            label: 'sw-settings-rule.detail.associations.eventActions',
            criteria: () => {
                return new Shopware.Data.Criteria();
            },
            addContext: {
                type: 'many-to-many',
                entity: 'event_action_rule',
                column: 'eventActionId',
                searchColumn: 'eventName',
                association: 'rules',
                criteria: () => {
                    return new Shopware.Data.Criteria();
                },
                gridColumns: [
                    {
                        property: 'eventName',
                        label: 'Event',
                        rawData: true,
                        sortable: true,
                        allowEdit: false,
                    },
                    {
                        property: 'title',
                        label: 'Title',
                        rawData: true,
                        sortable: true,
                        allowEdit: false,
                    },
                    {
                        property: 'active',
                        label: 'Active',
                        rawData: true,
                        sortable: true,
                        allowEdit: false,
                    },
                ],
            },
            repository: {
                search: () => {
                    const entities = [
                        { eventName: 'Foo', rules: [] },
                        { eventName: 'Bar', rules: [] },
                        { eventName: 'Baz', rules: [] },
                    ];

                    return Promise.resolve(createEntityCollectionMock('event_action', entities));
                },
            },
        });

        expect(wrapper.vm).toBeTruthy();
    });
});
