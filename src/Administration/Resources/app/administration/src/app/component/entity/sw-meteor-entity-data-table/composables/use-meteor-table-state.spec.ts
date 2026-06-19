/**
 * @sw-package framework
 */

import { useMeteorTableState } from './use-meteor-table-state';

describe('src/app/component/entity/sw-meteor-entity-data-table/composables/use-meteor-table-state', () => {
    it('emits cloned state changes and triggers one load per table state method', async () => {
        const emitStateChange = jest.fn();
        const load = jest.fn<Promise<void>, []>().mockResolvedValue();
        const tableState = useMeteorTableState({
            initialPage: () => 2,
            initialLimit: () => 10,
            initialSearchTerm: () => 'shirt',
            initialSort: () => ({
                property: 'name',
                direction: 'ASC',
            }),
            emitStateChange,
            load,
        });

        await tableState.setLimit(50);
        await tableState.setSearchTerm('needle');
        await tableState.setSort('createdAt', 'DESC');
        await tableState.setPage(3);

        expect(load).toHaveBeenCalledTimes(4);
        expect(emitStateChange).toHaveBeenLastCalledWith({
            page: 3,
            limit: 50,
            searchTerm: 'needle',
            sort: {
                property: 'createdAt',
                direction: 'DESC',
            },
        });
    });

    it('syncs changed initial props without emitting or loading', () => {
        let initialPage = 1;
        const emitStateChange = jest.fn();
        const load = jest.fn<Promise<void>, []>().mockResolvedValue();
        const tableState = useMeteorTableState({
            initialPage: () => initialPage,
            initialLimit: () => 25,
            initialSearchTerm: () => '',
            initialSort: () => null,
            emitStateChange,
            load,
        });

        initialPage = 4;
        tableState.syncStateFromProps();

        expect(tableState.state.value.page).toBe(4);
        expect(emitStateChange).not.toHaveBeenCalled();
        expect(load).not.toHaveBeenCalled();
    });
});
