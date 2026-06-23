/**
 * @sw-package framework
 */

/* eslint-disable sw-deprecation-rules/private-feature-declarations */

import Criteria from 'src/core/data/criteria.data';
import type {
    MeteorEntityTableCriteriaResolver,
    MeteorEntityTableLegacyColumn,
    MeteorEntityTableState,
} from '../sw-meteor-entity-data-table.types';

type UseMeteorEntityTableCriteriaOptions = {
    state: MeteorEntityTableState;
    columns: MeteorEntityTableLegacyColumn[];
    criteria?: Criteria | null;
    criteriaResolver?: MeteorEntityTableCriteriaResolver | null;
};

function cloneCriteria(criteria?: Criteria | null): Criteria {
    if (!criteria) {
        return new Criteria();
    }

    return Criteria.fromCriteria(criteria);
}

function getSortFields(activeColumn: MeteorEntityTableLegacyColumn | undefined, sortBy: string): string[] {
    if (!activeColumn) {
        return sortBy ? [sortBy] : [];
    }

    if (Array.isArray(activeColumn.sortFields) && activeColumn.sortFields.length > 0) {
        return activeColumn.sortFields;
    }

    const sortField = activeColumn.sortField ?? activeColumn.dataIndex ?? activeColumn.property;

    return sortField
        .split(',')
        .map((field) => field.trim())
        .filter(Boolean);
}

export function useMeteorEntityTableCriteria(options: UseMeteorEntityTableCriteriaOptions) {
    const buildCriteria = async (): Promise<Criteria | null> => {
        const criteria = cloneCriteria(options.criteria);
        criteria.setPage(options.state.page);
        criteria.setLimit(options.state.limit);

        if (options.state.searchTerm) {
            criteria.setTerm(options.state.searchTerm);
        }

        criteria.resetSorting();

        if (options.state.sortBy) {
            const activeColumn = options.columns.find((column) => {
                return [
                    column.property,
                    column.dataIndex,
                    column.sortField,
                ].includes(options.state.sortBy);
            });

            getSortFields(activeColumn, options.state.sortBy).forEach((field) => {
                criteria.addSorting(Criteria.sort(field, options.state.sortDirection, options.state.naturalSorting));
            });
        }

        if (!options.criteriaResolver) {
            return criteria;
        }

        return options.criteriaResolver(criteria);
    };

    return {
        buildCriteria,
    };
}
