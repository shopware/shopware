/**
 * @sw-package framework
 */

/* eslint-disable sw-deprecation-rules/private-feature-declarations */

import { ref } from 'vue';
import type { Ref } from 'vue';
import type { ApiContext } from '@shopware-ag/meteor-admin-sdk/es/_internals/data/EntityCollection';
import type CriteriaType from 'src/core/data/criteria.data';
import type { SwMeteorEntityDataTableColumn, SwMeteorEntityDataTableState } from '../sw-meteor-entity-data-table.types';
import type {
    SwMeteorEntityDataTableProps,
    SwMeteorEntityDataTableRecords,
} from '../sw-meteor-entity-data-table.internal-types';
import { resolveTotal, toArray } from '../sw-meteor-entity-data-table.utils';

type UseMeteorTableCriteriaOptions = {
    repository: () => SwMeteorEntityDataTableProps['repository'];
    criteria: () => CriteriaType | null | undefined;
    criteriaResolver: () => SwMeteorEntityDataTableProps['criteriaResolver'];
    context: () => SwMeteorEntityDataTableProps['context'];
    state: Ref<SwMeteorEntityDataTableState>;
    cloneState: () => SwMeteorEntityDataTableState;
    columns: () => SwMeteorEntityDataTableColumn[];
    resetInlineEdit: () => void;
    syncSelectedRecordsWithLoadedRecords: () => void;
    emitLoadSuccess: (payload: {
        records: SwMeteorEntityDataTableRecords;
        total: number;
        state: SwMeteorEntityDataTableState;
    }) => void;
    emitLoadError: (payload: { error: unknown; state: SwMeteorEntityDataTableState }) => void;
};

export function useMeteorTableCriteria(options: UseMeteorTableCriteriaOptions): {
    records: Ref<SwMeteorEntityDataTableRecords>;
    total: Ref<number>;
    loading: Ref<boolean>;
    buildCriteria: () => CriteriaType;
    load: () => Promise<void>;
    reload: () => Promise<void>;
} {
    const { Criteria } = Shopware.Data;
    const records: Ref<SwMeteorEntityDataTableRecords> = ref([]);
    const total = ref(0);
    const loading = ref(false);
    let latestLoadToken = 0;

    function buildCriteria(): CriteriaType {
        const criteria = options.criteria()
            ? Criteria.fromCriteria(options.criteria() as CriteriaType)
            : new Criteria(options.state.value.page, options.state.value.limit);

        criteria.setPage(options.state.value.page);
        criteria.setLimit(options.state.value.limit);
        criteria.setTerm(options.state.value.searchTerm);
        criteria.resetSorting();

        const activeSort = options.state.value.sort;

        if (!activeSort) {
            return criteria;
        }

        const activeColumn = options.columns().find((column) => column.property === activeSort.property);
        const sortFields = toArray(activeColumn?.sortField ?? activeSort.property);

        sortFields.forEach((field) => {
            criteria.addSorting(Criteria.sort(field, activeSort.direction, activeColumn?.naturalSorting === true));
        });

        return criteria;
    }

    async function load(): Promise<void> {
        const loadToken = (latestLoadToken += 1);
        loading.value = true;

        try {
            const searchContext = (options.context() ?? Shopware.Context.api) as typeof Shopware.Context.api;
            let criteria: CriteriaType | null = buildCriteria();
            const criteriaResolver = options.criteriaResolver();

            if (criteriaResolver) {
                criteria = await criteriaResolver({
                    criteria,
                    state: options.cloneState(),
                    context: searchContext as ApiContext,
                });
            }

            if (loadToken !== latestLoadToken) {
                return;
            }

            if (criteria === null) {
                records.value = [];
                total.value = 0;
                options.resetInlineEdit();
                options.syncSelectedRecordsWithLoadedRecords();

                options.emitLoadSuccess({
                    records: records.value,
                    total: total.value,
                    state: options.cloneState(),
                });

                return;
            }

            const result = await options.repository().search(criteria, searchContext);

            if (loadToken !== latestLoadToken) {
                return;
            }

            records.value = result;
            total.value = resolveTotal(result);
            options.resetInlineEdit();
            options.syncSelectedRecordsWithLoadedRecords();

            options.emitLoadSuccess({
                records: result,
                total: total.value,
                state: options.cloneState(),
            });
        } catch (error) {
            if (loadToken !== latestLoadToken) {
                return;
            }

            options.emitLoadError({
                error,
                state: options.cloneState(),
            });
        } finally {
            if (loadToken === latestLoadToken) {
                loading.value = false;
            }
        }
    }

    function reload(): Promise<void> {
        return load();
    }

    return {
        records,
        total,
        loading,
        buildCriteria,
        load,
        reload,
    };
}
