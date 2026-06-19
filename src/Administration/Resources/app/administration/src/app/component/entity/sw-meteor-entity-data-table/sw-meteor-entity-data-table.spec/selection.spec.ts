/**
 * @sw-package framework
 */

import {
    createListingConsumerWrapper,
    createWrapper,
    flushPromises,
    getTable,
    nextTick,
    records,
    registerSwMeteorEntityDataTableHooks,
} from './fixtures';
import type { TestRecord } from './fixtures';

describe('src/app/component/entity/sw-meteor-entity-data-table/selection', () => {
    registerSwMeteorEntityDataTableHooks();

    it('adds and removes a single row selection with a legacy-compatible selection-change payload', async () => {
        const wrapper = createWrapper({
            props: {
                selectable: true,
            },
        });

        await flushPromises();

        await wrapper.find('.mt-data-table-stub__select-row').trigger('click');
        await nextTick();

        expect(getTable(wrapper).props('selectedRows')).toEqual(['record-1']);

        await wrapper.find('.mt-data-table-stub__deselect-row').trigger('click');
        await nextTick();

        expect(getTable(wrapper).props('selectedRows')).toEqual([]);
        expect(wrapper.emitted('selection-change')).toEqual([
            [
                {
                    'record-1': records[0],
                },
                1,
            ],
            [
                {},
                0,
            ],
        ]);
        expect(wrapper.emitted('selected-ids-change')).toEqual([
            [
                ['record-1'],
            ],
            [
                [],
            ],
        ]);
    });

    it('merges and clears bulk selections without duplicating ids', async () => {
        const wrapper = createWrapper({
            props: {
                selectable: true,
            },
        });

        await flushPromises();

        await wrapper.find('.mt-data-table-stub__select-row').trigger('click');
        await wrapper.find('.mt-data-table-stub__select-all').trigger('click');
        await nextTick();

        expect(getTable(wrapper).props('selectedRows')).toEqual([
            'record-1',
            'record-2',
        ]);

        await wrapper.find('.mt-data-table-stub__deselect-all').trigger('click');
        await nextTick();

        expect(getTable(wrapper).props('selectedRows')).toEqual([]);
        expect(wrapper.emitted('selection-change')).toEqual([
            [
                {
                    'record-1': records[0],
                },
                1,
            ],
            [
                {
                    'record-1': records[0],
                    'record-2': records[1],
                },
                2,
            ],
            [
                {},
                0,
            ],
        ]);
        expect(wrapper.emitted('selected-ids-change')).toEqual([
            [
                ['record-1'],
            ],
            [
                [
                    'record-1',
                    'record-2',
                ],
            ],
            [
                [],
            ],
        ]);
    });

    it('clears bulk selections when selecting all already selected rows again', async () => {
        const wrapper = createWrapper({
            props: {
                selectable: true,
            },
        });

        await flushPromises();

        await wrapper.find('.mt-data-table-stub__select-all').trigger('click');
        await nextTick();

        expect(getTable(wrapper).props('selectedRows')).toEqual([
            'record-1',
            'record-2',
        ]);

        await wrapper.find('.mt-data-table-stub__select-all').trigger('click');
        await nextTick();

        expect(getTable(wrapper).props('selectedRows')).toEqual([]);
        expect(wrapper.emitted('selection-change')).toEqual([
            [
                {
                    'record-1': records[0],
                    'record-2': records[1],
                },
                2,
            ],
            [
                {},
                0,
            ],
        ]);
        expect(wrapper.emitted('selected-ids-change')).toEqual([
            [
                [
                    'record-1',
                    'record-2',
                ],
            ],
            [
                [],
            ],
        ]);
    });

    it('keeps listing mixin selected ID stores in sync through selection-change', async () => {
        const wrapper = createListingConsumerWrapper();

        await flushPromises();

        await wrapper.find('.mt-data-table-stub__select-row').trigger('click');
        await nextTick();

        const listingConsumer = wrapper.vm as unknown as { selection: Record<string, TestRecord> };

        expect(listingConsumer.selection).toEqual({
            'record-1': records[0],
        });
        expect(Shopware.Store.get('shopwareApps').selectedIds).toEqual(['record-1']);
        expect(Shopware.Store.get('swBulkEdit').selectedIds).toEqual(['record-1']);
    });
});
