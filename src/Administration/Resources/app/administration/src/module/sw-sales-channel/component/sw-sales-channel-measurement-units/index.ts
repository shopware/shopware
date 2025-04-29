/**
 * @sw-package discovery
 */

import { type PropType } from 'vue';
import template from './sw-sales-channel-measurement-units.html.twig';

import EntityCollection from 'src/core/data/entity-collection.data';

const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        salesChannel: {
            type: Object as PropType<Entity<'sales_channel' | 'sales_channel_domain'>>,
            required: true,
        },
    },

    data(): {
        measurementSystemConfig: EntityCollection<'measurement_system'> | null,
        defaultMeasurementSystem: { measurementSystemId: string, lengthUnitId: string, massUnitId: string } | null
    } {
        return {
            measurementSystemConfig: null,
            defaultMeasurementSystem: null,
        }
    },

    computed: {
        measurementSystemCriteria() {
            const criteria = new Criteria();
            criteria.addAssociation('units');

            criteria.getAssociation('units')
                .addFilter(Criteria.equals('default', true));

            return criteria;
        },

        lengthUnitCriteria() {
            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('type', 'length'));
            if (this.salesChannel?.measurementSystemId) {
                criteria.addFilter(Criteria.equals('measurementSystem.id', this.salesChannel.measurementSystemId));
            }

            return criteria;
        },

        massUnitCriteria() {
            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('type', 'mass'));
            if (this.salesChannel.measurementSystemId) {
                criteria.addFilter(Criteria.equals('measurementSystem.id', this.salesChannel.measurementSystemId));
            }

            return criteria;
        },
    },

    created() {
        this.defaultMeasurementSystem = {
            measurementSystemId: this.salesChannel.measurementSystemId,
            lengthUnitId: this.salesChannel.lengthUnitId,
            massUnitId: this.salesChannel.massUnitId,
        };
    },

    methods: {
        onMeasurementSystemChange(measurementSystemId: string) {
            const measurementSystemSelect = (
                this.$refs.measurementSystemSelect as { results: EntityCollection<'measurement_system'> }
            );

            const measurementSystem =
                measurementSystemSelect.results.get(measurementSystemId) as Entity<'measurement_system'>;

            this.$emit(
                'measurement-system-change',
                measurementSystemId,
                measurementSystem,
            );

            if (measurementSystemId === this.defaultMeasurementSystem.measurementSystemId) {
                this.salesChannel.lengthUnitId = this.defaultMeasurementSystem.lengthUnitId;
                this.salesChannel.massUnitId = this.defaultMeasurementSystem.massUnitId;

                return;
            }

            this.salesChannel.lengthUnitId = measurementSystem?.units?.filter(
                (unit): Entity<'measurement_display_unit'> => unit.type === 'length'
            ).first()?.id;

            this.salesChannel.massUnitId = measurementSystem?.units?.filter(
                (unit): Entity<'measurement_display_unit'> => unit.type === 'mass'
            ).first()?.id;
        },

        labelUnitCallback(item) {
            if (!item) {
                return '';
            }

            const name = item.translated?.name || item.name;
            const shortName = item.shortName || item.name;

            return `${name} (${shortName})`.trim();
        },

        labelUnitCallback(item) {
            if (!item) {
                return '';
            }

            const name = item.translated?.name || item.name;
            const shortName = item.shortName || item.name;

            return `${name} (${shortName})`.trim();
        },
    },
});
