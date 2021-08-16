export type TotalCountMode = 0 | 1 | 2;
export type OperatorModes = 'AND' | 'OR' | 'XOR';
export type OrderMode = 'ASC' | 'DESC';
export type MultiFilterMode = 'not' | 'multi';

export interface AggregationDefinition {
    type: string;
    name: string;
    field: string;
}

export interface TermsAggregationDefinition<AggregationDefinition> {
    limit: number | null;
    sort: SortingDefinition | null;
    aggregation: AggregationDefinition | null;
}

export interface FilterAggregationDefinition<AggregationDefinition> {
    filter: FilterDefinition;
    aggregation: AggregationDefinition | null;
}

export interface HistogramAggregation<AggregationDefinition> {
    interval: string | null;
    format: string | null;
    aggregation: AggregationDefinition | null;
    timeZone: string | null;
}

export interface RangeFilterType {
    lte?: string | number;
    lt?: string | number;
    gte?: string | number;
    gt?: string | number;
}

export interface FilterDefinition {
    field: string;
    parameters?: object;
    type: string;
    value: string | number;
}

export interface SortingDefinition {
    field: string;
    naturalSorting?: boolean;
    order: OrderMode;
}

export interface QueryDefinition {
    score?: number;
    query?: FilterDefinition;
}

export interface MultiFilterDefinition {
    type: MultiFilterMode;
    operator: OperatorModes;
    queries: QueryDefinition[];
}

export interface CriteriaDefinition {
    ids?: string;
    page?: number;
    limit?: number;
    term?: string;
    'total-count-model'?: number;
    'post-filter'?: FilterDefinition[];
    filter?: FilterDefinition[];
    associations?: {
        [key: string]: CriteriaDefinition;
    };
    aggregation?: object[];
    grouping?: string[];
    groupFields?: string[];
    sort?: SortingDefinition[];
    queries?: QueryDefinition[];
}

export class Criteria {
    parse(): CriteriaDefinition;

    setIds(ids: string[]): Criteria;

    setTotalCountMode(mode: TotalCountMode): Criteria;

    setPage(page: number): Criteria;

    setLimit(limit: number): Criteria;

    setTerm(term: string): Criteria;

    addFilter(filter: FilterDefinition): Criteria;

    addPostFilter(filter: FilterDefinition): Criteria;

    addSorting(sorting: SortingDefinition): Criteria;

    addQuery(
        filter: FilterDefinition,
        score: number,
        scoreField?: string
    ): Criteria;

    addGroupField(groupField: string): Criteria;

    addGrouping(field: string): Criteria;

    addAggregation(aggregation: AggregationDefinition): Criteria;

    addAssociation(): Criteria;

    getAssociation(): Criteria;

    private getAssociationCriteria(): Criteria;

    hasAssociation(): boolean;

    resetSorting(): void;

    static avg(name: string, field: string): AggregationDefinition;

    static count(name: string, field: string): AggregationDefinition;

    static max(name: string, field: string): AggregationDefinition;

    static min(name: string, field: string): AggregationDefinition;

    static stats(name: string, field: string): AggregationDefinition;

    static sum(name: string, field: string): AggregationDefinition;

    static terms(
        name: string,
        field: string,
        limit?: number | null,
        sort?: SortingDefinition | null,
        aggregation?: AggregationDefinition | null
    ): TermsAggregationDefinition<AggregationDefinition>;

    static filter(
        name: string,
        filter: FilterDefinition[],
        aggregation: AggregationDefinition
    ): FilterAggregationDefinition<AggregationDefinition>;

    static histogram(
        name: string,
        field: string,
        interval: string | null,
        format: string | null,
        aggregation: AggregationDefinition | null,
        timeZone: string | null
    ): HistogramAggregation<AggregationDefinition>;

    static sort(
        field: string,
        order?: string,
        naturalSorting?: boolean
    ): SortingDefinition;

    static naturalSorting(field: string, order?: string): SortingDefinition;

    static contains(field: string, value: string): FilterDefinition;

    static prefix(field: string, value: string): FilterDefinition;

    static suffix(field: string, value: string): FilterDefinition;

    static equalsAny(field: string, value: string[]): FilterDefinition;

    static range(field: string, range: RangeFilterType): FilterDefinition;

    static equals(
        field: string,
        value: string | number | boolean | null
    ): FilterDefinition;

    static not(
        operator: OperatorModes,
        queries?: QueryDefinition[]
    ): MultiFilterDefinition;

    static multi(
        operator: OperatorModes,
        queries?: QueryDefinition[]
    ): MultiFilterDefinition;
}
