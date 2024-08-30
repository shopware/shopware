/**
 * @package admin
 */

import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-one-to-many-grid', { sync: true }), {
        props: {
            columns: [
                {
                    property: 'name',
                    label: 'Name',
                },
                {
                    property: 'shortCode',
                    label: 'Short code',
                },
            ],
            collection: [
                {
                    name: 'name',
                    shortCode: 'shortCode',
                },
                {
                    name: 'name',
                    shortCode: 'shortCode',
                },
            ],
            allowDelete: true,
        },
        global: {
            provide: {
                repositoryFactory: {
                    create: () => {
                        return Promise.resolve({
                            total: 0,
                            criteria: {
                                page: 1,
                                limit: 25,
                            },
                        });
                    },
                },
            },
            renderStubDefaultSlot: true,
            stubs: {
                'sw-pagination': true,
                'sw-checkbox-field': true,
                'sw-context-button': true,
                'sw-context-menu-item': true,
                'sw-icon': true,
                'sw-data-grid-settings': true,
                'sw-data-grid-column-boolean': true,
                'sw-data-grid-inline-edit': true,
                'router-link': true,
                'sw-button': true,
                'sw-data-grid-skeleton': true,
            },
        },
    });
}

describe('app/component/entity/sw-one-to-many-grid', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should enable the context menu delete item', async () => {
        const wrapper = await createWrapper();

        const firstRow = wrapper.find('.sw-data-grid__row--1');
        const firstRowActions = firstRow.find('.sw-data-grid__cell--actions');
        const firstRowActionDelete = firstRowActions.find('.sw-one-to-many-grid__delete-action');

        expect(firstRowActionDelete.exists()).toBeTruthy();
        expect(firstRowActionDelete.attributes().disabled).toBeFalsy();
    });

    it('should disable the context menu delete item', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            allowDelete: false,
        });

        const firstRow = wrapper.find('.sw-data-grid__row--1');
        const firstRowActions = firstRow.find('.sw-data-grid__cell--actions');
        const firstRowActionDelete = firstRowActions.find('.sw-one-to-many-grid__delete-action');

        expect(firstRowActionDelete.exists()).toBeTruthy();
        expect(firstRowActionDelete.attributes().disabled).toBeTruthy();
    });
});
