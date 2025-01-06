import type { PropType } from 'vue';
import template from './sw-string-filter.html.twig';

const { Criteria } = Shopware.Data;

/**
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    compatConfig: Shopware.compatConfig,

    props: {
        filter: {
            type: Object,
            required: true,
        },
        active: {
            type: Boolean,
            required: true,
        },
        criteriaFilterType: {
            type: String as PropType<'contains' | 'equals' | 'equalsAny' | 'prefix' | 'suffix'>,
            required: false,
            default: 'contains',
            validValues: [
                'contains',
                'equals',
                'equalsAny',
                'prefix',
                'suffix',
            ],
            validator(value: string): boolean {
                return [
                    'contains',
                    'equals',
                    'equalsAny',
                    'prefix',
                    'suffix',
                ].includes(value);
            },
        },
    },

    methods: {
        updateFilter(newValue: string): void {
            if (!newValue || typeof this.filter.property !== 'string') {
                this.resetFilter();

                return;
            }

            let filterValue: string | string[] = newValue;
            let filterCriteria;

            if (this.criteriaFilterType === 'equalsAny') {
                filterValue = newValue.split(',').map((e) => e.trim());
                filterCriteria = Criteria.equalsAny(this.filter.property, filterValue);
            } else {
                filterCriteria = Criteria[this.criteriaFilterType](this.filter.property, filterValue);
            }

            this.$emit('filter-update', this.filter.name, [filterCriteria], filterValue);
        },

        resetFilter(): void {
            this.$emit('filter-reset', this.filter.name);
        },
    },
});
