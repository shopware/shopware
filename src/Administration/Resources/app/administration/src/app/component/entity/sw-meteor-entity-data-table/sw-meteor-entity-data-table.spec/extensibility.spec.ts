/**
 * @sw-package framework
 */

import {
    componentName,
    createWrapper,
    flushPromises,
    getSetupState,
    getTable,
    isNativeShopwareComponentName,
    nextTick,
    overrideComponentSetup,
    ref,
    registerSwMeteorEntityDataTableHooks,
} from './fixtures';
import type { SwMeteorEntityDataTable, SwMeteorEntityDataTableState } from './fixtures';

describe('src/app/component/entity/sw-meteor-entity-data-table/extensibility', () => {
    registerSwMeteorEntityDataTableHooks();

    it('exposes the new public setup API without legacy placeholders', () => {
        const wrapper = createWrapper();
        const setupStateKeys = Object.keys(getSetupState(wrapper));

        expect(setupStateKeys).toEqual(
            expect.arrayContaining([
                'records',
                'total',
                'loading',
                'state',
                'selectedIds',
                'resolvedColumns',
                'buildCriteria',
                'load',
                'reload',
                'setPage',
                'setLimit',
                'setSearchTerm',
                'setSort',
                'setSelectedIds',
            ]),
        );
        expect(setupStateKeys).not.toEqual(
            expect.arrayContaining([
                'dataSource',
                'totalItems',
                'page',
                'limit',
                'sortBy',
                'sortDirection',
                'searchTerm',
                'columnChanges',
                'normalizedColumns',
                'deleteItem',
                'deleteItems',
            ]),
        );
    });

    it('allows overrideComponentSetup to override public setup state', async () => {
        const overrideRecords = [
            {
                id: 'override-record',
                name: 'Override record',
            },
        ];

        overrideComponentSetup<typeof SwMeteorEntityDataTable>()(componentName, () => ({
            records: ref(overrideRecords),
            total: ref(1),
            state: ref({
                page: 7,
                limit: 100,
                searchTerm: 'override',
            }),
        }));

        const wrapper = createWrapper();

        await nextTick();

        const table = getTable(wrapper);

        expect(table.props('dataSource')).toEqual(overrideRecords);
        expect(table.props('paginationTotalItems')).toBe(1);
        expect(table.props('currentPage')).toBe(7);
        expect(table.props('paginationLimit')).toBe(100);
        expect(table.props('searchValue')).toBe('override');
    });

    it('converts legacy Options API overrides queued through Shopware.Component.override', async () => {
        const warnSpy = jest.spyOn(console, 'warn').mockImplementation(() => {});

        expect(isNativeShopwareComponentName(componentName)).toBe(true);

        Shopware.Component.override(componentName, {
            methods: {
                setPage(
                    this: {
                        $super: (methodName: string, page: number) => unknown;
                        state: SwMeteorEntityDataTableState;
                    },
                    page: number,
                ) {
                    this.$super('setPage', page);
                    this.state.page += 10;
                },
            },
        });

        const wrapper = createWrapper();
        await flushPromises();
        await nextTick();

        await getTable(wrapper).find('.mt-data-table-stub__page').trigger('click');
        await nextTick();

        expect(getTable(wrapper).props('currentPage')).toBe(13);

        warnSpy.mockRestore();
    });
});
