/**
 * @sw-package framework
 */

/* eslint-disable sw-deprecation-rules/private-feature-declarations */

import Criteria from 'src/core/data/criteria.data';
import type {
    MeteorEntityTableColumnDefinition,
    MeteorEntityTableCriteriaTransform,
    MeteorEntityTableState,
} from '../sw-meteor-entity-data-table.types';
import { getStateSnapshot } from '../sw-meteor-entity-data-table.utils';

type UseMeteorEntityTableCriteriaOptions = {
    state: MeteorEntityTableState;
    getColumns: () => MeteorEntityTableColumnDefinition[];
    getCriteria: () => Criteria | null | undefined;
    getCriteriaTransform: () => MeteorEntityTableCriteriaTransform | null | undefined;
    getSearchTerm: () => string;
};

function cloneCriteria(criteria?: Criteria | null): Criteria {
    if (!criteria) {
        return new Criteria();
    }

    return Criteria.fromCriteria(criteria);
}

function getSortFields(activeColumn: MeteorEntityTableColumnDefinition | undefined, sortBy: string): string[] {
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
        const baseCriteria = options.getCriteria() ?? null;
        const criteria = cloneCriteria(baseCriteria);
        criteria.setPage(options.state.page);
        criteria.setLimit(options.state.limit);

        const searchTerm = options.getSearchTerm();

        if (searchTerm) {
            criteria.setTerm(searchTerm);
        }

        criteria.resetSorting();

        if (options.state.sortBy) {
            const activeColumn = options.getColumns().find((column) => {
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

        const criteriaTransform = options.getCriteriaTransform();

        if (!criteriaTransform) {
            return criteria;
        }

        return criteriaTransform(criteria, getStateSnapshot(options.state), {
            baseCriteria,
            columns: options.getColumns(),
            searchTerm,
        });
    };

    return {
        buildCriteria,
    };
}
