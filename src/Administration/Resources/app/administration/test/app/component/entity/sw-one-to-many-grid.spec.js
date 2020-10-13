import { shallowMount } from '@vue/test-utils';
import 'src/app/component/data-grid/sw-data-grid';
import 'src/app/component/entity/sw-one-to-many-grid';

function createWrapper() {
    return shallowMount(Shopware.Component.build('sw-one-to-many-grid'), {
        propsData: {
            columns: [
                {
                    property: 'name',
                    label: 'Name'
                },
                {
                    property: 'shortCode',
                    label: 'Short code'
                }
            ],
            collection: [
                {
                    name: 'name',
                    shortCode: 'shortCode'
                },
                {
                    name: 'name',
                    shortCode: 'shortCode'
                }
            ],
            allowDelete: true
        },

        provide: {
            repositoryFactory: {
                create: () => {
                    return Promise.resolve({
                        total: 0,
                        criteria: {
                            page: 1,
                            limit: 25
                        }
                    });
                }
            }
        },

        stubs: {
            'sw-pagination': true,
            'sw-checkbox-field': true,
            'sw-context-button': true,
            'sw-context-menu-item': true
        }
    });
}

describe('app/component/entity/sw-one-to-many-grid', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should enable the context menu delete item', async () => {
        const wrapper = createWrapper();

        const firstRow = wrapper.find('.sw-data-grid__row--1');
        const firstRowActions = firstRow.find('.sw-data-grid__cell--actions');
        const firstRowActionDelete = firstRowActions.find('.sw-one-to-many-grid__delete-action');

        expect(firstRowActionDelete.exists()).toBeTruthy();
        expect(firstRowActionDelete.attributes().disabled).toBeFalsy();
    });

    it('should disable the context menu delete item', async () => {
        const wrapper = createWrapper();

        await wrapper.setProps({
            allowDelete: false
        });

        const firstRow = wrapper.find('.sw-data-grid__row--1');
        const firstRowActions = firstRow.find('.sw-data-grid__cell--actions');
        const firstRowActionDelete = firstRowActions.find('.sw-one-to-many-grid__delete-action');

        expect(firstRowActionDelete.exists()).toBeTruthy();
        expect(firstRowActionDelete.attributes().disabled).toBeTruthy();
    });
});
