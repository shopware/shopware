import { mount } from '@vue/test-utils';

/**
 * @package inventory
 */
describe('src/module/sw-settings-listing/component/sw-settings-listing-option-criteria-grid', () => {
    const customFieldRelations = [];
    const customFields = [
        {
            name: 'my_first_custom_field',
            config: {
                label: { 'en-GB': 'asperiores sint dolore' },
            },
        },
    ];

    async function createWrapper() {
        return mount(
            await wrapTestComponent('sw-settings-listing-option-criteria-grid', {
                sync: true,
            }),
            {
                global: {
                    renderStubDefaultSlot: true,
                    provide: {
                        repositoryFactory: {
                            create: (repository) => {
                                if (repository === 'custom_field_set_relation') {
                                    return {
                                        search: () => Promise.resolve(customFieldRelations),
                                    };
                                }

                                if (repository === 'custom_field') {
                                    return {
                                        search: () => Promise.resolve(customFields),
                                        get: () => Promise.resolve(),
                                    };
                                }

                                return { search: () => Promise.resolve() };
                            },
                        },
                    },
                    stubs: {
                        'sw-card': {
                            template: '<div><slot></slot></div>',
                        },
                        'sw-empty-state': {
                            template: '<div class="sw-empty-state"></div>',
                        },
                        'sw-data-grid': await wrapTestComponent('sw-data-grid'),
                        'sw-checkbox-field': await wrapTestComponent('sw-checkbox-field'),
                        'sw-checkbox-field-deprecated': await wrapTestComponent('sw-checkbox-field-deprecated', {
                            sync: true,
                        }),
                        'sw-icon': {
                            template: '<i></i>',
                        },
                        'sw-base-field': await wrapTestComponent('sw-base-field'),
                        'sw-block-field': await wrapTestComponent('sw-block-field'),
                        'sw-field-error': await wrapTestComponent('sw-field-error'),
                        'sw-context-button': await wrapTestComponent('sw-context-button'),
                        'sw-entity-single-select': await wrapTestComponent('sw-entity-single-select'),
                        'sw-select-base': await wrapTestComponent('sw-select-base'),
                        'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
                        'sw-select-result': await wrapTestComponent('sw-select-result'),
                        'sw-popover': await wrapTestComponent('sw-popover'),
                        'sw-popover-deprecated': {
                            props: ['popoverClass'],
                            template: `
                    <div class="sw-popover" :class="popoverClass">
                        <slot></slot>
                    </div>`,
                        },
                        'sw-loader': true,
                        'sw-context-menu-item': true,
                        'sw-context-menu': true,
                        'sw-single-select': true,
                        'sw-data-grid-column-boolean': true,
                        'sw-data-grid-inline-edit': true,
                        'router-link': true,
                        'sw-button': true,
                        'sw-data-grid-skeleton': true,
                        'sw-data-grid-settings': true,
                        'sw-product-variant-info': true,
                        'sw-highlight-text': true,
                        'sw-inheritance-switch': true,
                        'sw-ai-copilot-badge': true,
                        'sw-help-text': true,
                    },
                },
                props: {
                    productSortingEntity: {
                        label: 'Price descending',
                        fields: [
                            {
                                field: 'product.cheapestPrice',
                                order: 'desc',
                                priority: 0,
                                naturalSorting: 0,
                            },
                            {
                                field: 'product.stock',
                                order: 'desc',
                                priority: 3,
                                naturalSorting: 0,
                            },
                            {
                                field: 'product.cheapestPrice',
                                order: 'asc',
                                priority: 2,
                                naturalSorting: 1,
                            },
                        ],
                    },
                },
            },
        );
    }

    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
        await flushPromises();
    });

    it('should be a Vue.js Component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should sort criterias by their position', async () => {
        function getRowValuesAt(index) {
            function getContentOfCell(columnName) {
                return wrapper.find(`.sw-data-grid__row--${index} .sw-data-grid__cell--${columnName}`).text();
            }

            return {
                field: getContentOfCell('field'),
                order: getContentOfCell('order'),
                priority: getContentOfCell('priority'),
            };
        }

        for (let i = 0; i < wrapper.vm.productSortingEntity.fields.length - 1; i += 1) {
            const priorityOfCurrentRow = Number(getRowValuesAt(i).priority);
            const priorityOfNextRow = Number(getRowValuesAt(i + 1).priority);

            expect(priorityOfCurrentRow).toBeGreaterThanOrEqual(priorityOfNextRow);
        }
    });

    it('should strip custom field path', async () => {
        const strippedCustomFieldPath = wrapper.vm.stripCustomFieldPath('customFields.my_first_custom_field');

        expect(strippedCustomFieldPath).toBe('my_first_custom_field');
    });

    it('should return true when giving it a custom field', async () => {
        const isItemACustomField = wrapper.vm.isItemACustomField('customFields.my_first_custom_field');

        expect(isItemACustomField).toBe(true);
    });

    it('should return true if newly added criteria already exists', async () => {
        const isCriteriaAlreadyUsed = wrapper.vm.criteriaIsAlreadyUsed('product.stock');

        expect(isCriteriaAlreadyUsed).toBe(true);
    });

    it('should return false if newly added criteria does not already exist', async () => {
        const isCriteriaAlreadyUsed = wrapper.vm.criteriaIsAlreadyUsed('product.name');

        expect(isCriteriaAlreadyUsed).toBe(false);
    });

    it('should emit an event when newly added custom field is not already used', async () => {
        wrapper.vm.onAddCriteria('product.name');

        const criteriaAddEvent = wrapper.emitted()['criteria-add'];

        expect(criteriaAddEvent[0]).toContain('product.name');
    });

    it('should create an error notification when newly added custom field is already used', async () => {
        // mocking createNotificationError function
        wrapper.vm.createNotificationError = jest.fn();

        wrapper.vm.onAddCriteria('product.stock');

        expect(wrapper.vm.createNotificationError).toHaveBeenCalled();

        wrapper.vm.createNotificationError.mockRestore();
    });

    it('should return custom fields name', async () => {
        await wrapper.setProps({
            productSortingEntity: {
                ...{
                    fields: [
                        {
                            field: 'customFields.custom_sports_necessitatibus_rerum_fugiat',
                            name: '8d863f0747d84544a767ea77a239b0ec',
                            naturalSorting: 0,
                            order: 'asc',
                            priority: 1,
                        },
                        {
                            field: 'customFields.custom_movies_aspernatur_enim_error',
                            name: '300d8964173b47d79cf4e348b09fce08',
                            naturalSorting: 0,
                            order: 'asc',
                            priority: 1,
                        },
                    ],
                },
            },
        });

        let getProductSortingFieldsByName = wrapper.vm.getProductSortingFieldsByName();

        expect(getProductSortingFieldsByName).toEqual([
            '8d863f0747d84544a767ea77a239b0ec',
            '300d8964173b47d79cf4e348b09fce08',
        ]);

        await wrapper.vm.$nextTick();

        getProductSortingFieldsByName = wrapper.vm.getProductSortingFieldsByName({
            field: 'customFields.custom_movies_aspernatur_enim_error',
        });

        expect(getProductSortingFieldsByName).toEqual([
            '8d863f0747d84544a767ea77a239b0ec',
        ]);
    });

    it('should change productSortingEntity when add custom field', async () => {
        await wrapper.setProps({
            productSortingEntity: {
                ...{
                    fields: [
                        {
                            field: 'customField',
                            naturalSorting: 0,
                            order: 'asc',
                            priority: 1,
                        },
                    ],
                },
            },
        });
        await flushPromises();

        await wrapper.find('.sw-data-grid__row--0 .sw-select__selection').trigger('click');
        await flushPromises();

        const results = wrapper.findAll('.sw-select-result')[0];
        await results.trigger('click');
        await flushPromises();

        expect(wrapper.vm.productSortingEntity.fields).toEqual([
            {
                field: 'customFields.my_first_custom_field',
                name: undefined,
                naturalSorting: 0,
                order: 'asc',
                priority: 1,
            },
        ]);
    });
});
