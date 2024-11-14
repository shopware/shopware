/**
 * @package admin
 */

import { mount } from '@vue/test-utils';
import 'src/app/component/form/select/entity/sw-entity-advanced-selection-modal';

const EntityColumnsFixture = [
    {
        property: 'name',
        label: 'name',
        routerLink: 'sw.product.detail',
        inlineEdit: 'string',
        allowResize: true,
        primary: true,
    },
    {
        property: 'customNumber',
        naturalSorting: true,
        label: 'custom number',
        align: 'right',
        allowResize: true,
    },
    {
        property: 'assoc.something',
        naturalSorting: true,
        label: 'something',
        align: 'right',
        allowResize: true,
    },
    {
        property: 'some.changed.association.value',
        naturalSorting: true,
        label: 'some value',
        align: 'right',
        allowResize: true,
    },
];

const EntityFiltersFixture = {
    'custom-filter': {
        property: 'customNumber',
        label: 'custom number',
        numberType: 'int',
        step: 1,
        min: 0,
        fromPlaceholder: '...',
        toPlaceholder: '...',
    },
    'data-filter': {
        property: 'another.data',
        label: 'data number',
        numberType: 'int',
        step: 1,
        min: 0,
        fromPlaceholder: '...',
        toPlaceholder: '...',
    },
    'nested-filter': {
        property: 'some.deeply.nested.number',
        label: 'nested number',
        numberType: 'int',
        step: 1,
        min: 0,
        fromPlaceholder: '...',
        toPlaceholder: '...',
    },
};

const createAdvancedSelectionModal = async (customOptions) => {
    const options = {
        propsData: {
            entityName: 'test',
            entityDisplayText: 'Test',
            emptyImagePath: '',
            storeKey: 'advancedSearch.test',
            entityColumns: EntityColumnsFixture,
            entityFilters: EntityFiltersFixture,
        },
        global: {
            stubs: {
                'sw-icon': true,
                'sw-modal': true,
                'sw-card': true,
                'sw-card-filter': true,
                'sw-ignore-class': true,
                'sw-extension-component-section': true,
                'sw-empty-state': true,
                'sw-loader': true,
                'sw-button': true,
                'sw-simple-search-field': true,
                'sw-context-menu': true,
                'sw-filter-panel': true,
                'sw-entity-listing': true,
                'sw-entity-advanced-selection-modal-grid': true,
            },
            provide: {
                acl: {
                    can: () => {
                        return true;
                    },
                },
                repositoryFactory: {
                    create: () => {
                        return {
                            get: (value) => Promise.resolve({ id: value, name: value }),
                            search: () => Promise.resolve(null),
                        };
                    },
                },
                filterFactory: {
                    create: (entityName, filters) => {
                        return Object.entries(filters);
                    },
                },
                filterService: {
                    getStoredCriteria: () => {
                        return Promise.resolve([]);
                    },
                },
                shortcutService: {
                    startEventListener() {},
                    stopEventListener() {},
                },
                searchRankingService: {
                    getSearchFieldsByEntity() {
                        return Promise.resolve(null);
                    },
                    buildSearchQueriesForEntity: () => {
                        return null;
                    },
                },
            },
        },
    };

    return mount(
        await wrapTestComponent('sw-entity-advanced-selection-modal', {
            sync: true,
        }),
        {
            ...options,
            ...customOptions,
        },
    );
};

describe('components/sw-entity-advanced-selection-modal', () => {
    it('should be a Vue.js component', async () => {
        const searchModal = await createAdvancedSelectionModal();

        expect(searchModal.vm).toBeTruthy();
    });

    it('should respect the entered search term in criteria', async () => {
        const searchModal = await createAdvancedSelectionModal();

        const searchTerm = 'custom search term';
        searchModal.vm.onSearch(searchTerm);

        const criteria = searchModal.vm.entityCriteria;
        expect(criteria.term).toBe(searchTerm);
    });

    it('should have required associations based on columns and filters', async () => {
        const searchModal = await createAdvancedSelectionModal();
        const allEntityAssociations = searchModal.vm.allEntityAssociations;

        expect(allEntityAssociations).toEqual(
            new Set([
                'assoc',
                'another',
                'some.deeply.nested',
                'some.changed.association',
            ]),
        );
    });

    it('should have the correct filter number', async () => {
        const searchModal = await createAdvancedSelectionModal();
        expect(searchModal.vm.activeFilterNumber).toBe(0);

        // simulate applied filters
        const appliedFilters = [
            {
                name: 'some-broken-filter',
            },
        ];
        searchModal.vm.updateCriteria(appliedFilters);
        expect(searchModal.vm.activeFilterNumber).toEqual(appliedFilters.length);
    });

    it('should emit the selected items on apply', async () => {
        const searchModal = await createAdvancedSelectionModal();

        // simulate a selection and click on apply
        searchModal.vm.onSelectionChange({
            'item-one': 'one',
            'item-two': 'two',
        });
        searchModal.vm.onApply();

        // assert proper event dispatch with correct data
        const selectionSubmitEvent = searchModal.emitted('selection-submit');
        expect(selectionSubmitEvent).toHaveLength(1);
        expect(selectionSubmitEvent[0]).toEqual([
            [
                'one',
                'two',
            ],
        ]);
        expect(searchModal.emitted('modal-close')).toHaveLength(1);
    });

    it('should get assignment properties', async () => {
        const searchModal = await createAdvancedSelectionModal();

        await searchModal.setProps({
            entityName: 'rule',
        });
        const assignmentProperties = searchModal.vm.assignmentProperties;

        expect(assignmentProperties).toContain('productPrices');
        expect(assignmentProperties).toContain('paymentMethods');
    });
});
