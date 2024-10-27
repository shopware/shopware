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

            if (this.criteriaFilterType === 'equalsAny') {
                newValue = newValue.split(',').map(e => e.trim());
            }

            const filterCriteria = [
                Criteria[this.criteriaFilterType](this.filter.property, newValue),
            ];

            this.$emit('filter-update', this.filter.name, filterCriteria, newValue);
        },

        resetFilter(): void {
            this.$emit('filter-reset', this.filter.name);
        },
    },
});
