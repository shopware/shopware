/* eslint-disable max-len */
import { shallowMount } from '@vue/test-utils_v3';
import EntityCollection from 'src/core/data/entity-collection.data';

function createEntityCollectionMock(entityName, items = []) {
    return new EntityCollection('/route', entityName, {}, {}, items, items.length);
}

async function createWrapper(entityContext) {
    return shallowMount(await wrapTestComponent('sw-settings-rule-add-assignment-listing', { sync: true }), {
        props: {
            ruleId: 'uuid1',
            entityContext: entityContext,
        },
        global: {
            stubs: {
                'sw-card': {
                    template: '<div class="sw-card"><slot></slot></div>',
                },
                'sw-data-grid': await wrapTestComponent('sw-data-grid'),
                'sw-pagination': true,
                'sw-data-grid-skeleton': true,
                'sw-checkbox-field': true,
                'sw-icon': true,
                'sw-button': true,
            },
            provide: {},
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
