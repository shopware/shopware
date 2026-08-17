/**
 * @sw-package framework
 * @private
 */

import { type ComposableDescriptor, methodMembers, refMembers } from '../types';

const LISTING_DESCRIPTOR: ComposableDescriptor = {
    id: 'listing',
    mixinNames: ['listing'],
    import: { source: 'src/app/composables/use-listing', name: 'useListing' },
    members: {
        ...refMembers([
            'page',
            'limit',
            'total',
            'sortBy',
            'sortDirection',
            'naturalSorting',
            'selection',
            'term',
            'disableRouteParams',
            'searchConfigEntity',
            'entitySearchable',
            'freshSearchTerm',
            'previousRouteName',
            'storeKey',
            'filterCriteria',
            'maxPage',
            'routeName',
            'selectionArray',
            'selectionCount',
            'searchRankingFields',
            'currentSortBy',
        ]),
        ...methodMembers([
            'updateData',
            'updateRoute',
            'resetListing',
            'getMainListingParams',
            'updateSelection',
            'onPageChange',
            'onSearch',
            'onSwitchFilter',
            'onSort',
            'onSortColumn',
            'onRefresh',
            'isValidTerm',
            'addQueryScores',
            'parseBooleanQueryParams',
            'updateCriteria',
        ]),
    },
    // The whole listing state, which the route watcher, the lifecycle hook and every on* handler
    // read back, plus the four methods they route through.
    internallyReferencedMembers: [
        'page',
        'limit',
        'total',
        'sortBy',
        'sortDirection',
        'naturalSorting',
        'selection',
        'term',
        'disableRouteParams',
        'searchConfigEntity',
        'entitySearchable',
        'freshSearchTerm',
        'previousRouteName',
        'storeKey',
        'filterCriteria',
        'selectionArray',
        'updateData',
        'updateRoute',
        'resetListing',
        'isValidTerm',
        'parseBooleanQueryParams',
    ],
    // The two services the mixin injected, which the composable resolves itself, and the `filters`
    // computed it defaulted to an empty list — a component that reads one without declaring it
    // would read nothing after the migration.
    unmappedMembers: [
        'feature',
        'searchRankingService',
        'filters',
    ],
    // `filters` was the mixin's own computed and the component's override at once, so it arrives as
    // an optional getter: a component without filters keeps the mixin's empty list.
    callbackArgs: [
        { name: 'filters', kind: 'getter', optional: true },
    ],
    scaffold: {
        iocMember: 'getList',
        configKeys: [
            'page',
            'limit',
            'total',
            'sortBy',
            'sortDirection',
            'naturalSorting',
            'selection',
            'term',
            'disableRouteParams',
            'searchConfigEntity',
            'entitySearchable',
            'freshSearchTerm',
            'storeKey',
            'filterCriteria',
        ],
        checks: [
            'getList() is passed to useListing() and still resolves everything it reads and writes',
            'the initial load runs on mounted now, one hook later than the mixin loaded it',
            'route parameter handling, which the composable owns from here on',
        ],
        forcesPartial: true,
    },
};

export default LISTING_DESCRIPTOR;
