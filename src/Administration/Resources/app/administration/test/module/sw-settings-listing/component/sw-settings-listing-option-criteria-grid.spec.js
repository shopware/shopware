import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-settings-listing/component/sw-settings-listing-option-criteria-grid';
import 'src/app/component/data-grid/sw-data-grid';
import 'src/app/component/form/sw-checkbox-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-field-error';
import 'src/app/component/context-menu/sw-context-button';

describe('src/module/sw-settings-listing/component/sw-settings-listing-option-criteria-grid', () => {
    const customFieldRelations = [];
    const customFields = [{ name: 'my_first_custom_field' }];

    function createWrapper() {
        return shallowMount(Shopware.Component.build('sw-settings-listing-option-criteria-grid'), {
            mocks: {
                $tc: translationKey => translationKey,
                $te: translationKey => translationKey,
                $t: translationKey => translationKey,
                $device: { onResize: () => {} }
            },
            provide: {
                next5983: true,
                repositoryFactory: {
                    create: repository => {
                        if (repository === 'custom_field_set_relation') {
                            return { search: () => Promise.resolve(customFieldRelations) };
                        }

                        if (repository === 'custom_field') {
                            return { search: () => Promise.resolve(customFields) };
                        }

                        return { search: () => Promise.resolve() };
                    }
                }
            },
            stubs: {
                'sw-card': {
                    template: '<div><slot></slot></div>'
                },
                'sw-empty-state': {
                    template: '<div class="sw-empty-state"></div>'
                },
                'sw-data-grid': Shopware.Component.build('sw-data-grid'),
                'sw-checkbox-field': Shopware.Component.build('sw-checkbox-field'),
                'sw-icon': {
                    template: '<i></i>'
                },
                'sw-base-field': Shopware.Component.build('sw-base-field'),
                'sw-block-field': Shopware.Component.build('sw-block-field'),
                'sw-field-error': Shopware.Component.build('sw-field-error'),
                'sw-context-button': Shopware.Component.build('sw-context-button')
            },
            propsData: {
                productSortingEntity: {
                    label: 'Price descending',
                    fields: [
                        {
                            field: 'product.listingPrices',
                            order: 'desc',
                            priority: 0,
                            naturalSorting: 0
                        },
                        {
                            field: 'product.stock',
                            order: 'desc',
                            priority: 3,
                            naturalSorting: 0
                        },
                        {
                            field: 'product.listingPrices',
                            order: 'asc',
                            priority: 2,
                            naturalSorting: 1
                        }
                    ]
                }
            }
        });
    }

    let wrapper;

    beforeEach(() => {
        wrapper = createWrapper();
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
                priority: getContentOfCell('priority')
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
});
