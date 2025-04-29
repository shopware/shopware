/**
 * @sw-package discovery
 */

import { type PropType } from 'vue';
import EntityCollection from 'src/core/data/entity-collection.data';
import template from './sw-sales-channel-measurement-units.html.twig';

const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default Shopware.Component.wrapComponentConfig({
    template,

    inject: [
        'systemConfigApiService',
    ],

    props: {
        salesChannel: {
            type: Object as PropType<Entity<'sales_channel'>>,
            required: true,
        },

        domain: {
            type: Object as PropType<Entity<'sales_channel_domain'>>,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            measurementSystemConfig: null,
            defaultMeasurment: [],
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
            if (this.measurementSystemId) {
                criteria.addFilter(Criteria.equals('measurementSystem.id', this.measurementSystemId));
            }

            return criteria;
        },

        massUnitCriteria() {
            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('type', 'mass'));
            if (this.measurementSystemId) {
                criteria.addFilter(Criteria.equals('measurementSystem.id', this.measurementSystemId));
            }

            return criteria;
        },

        measurementSystemId: {
            set(measurementSystemId) {
                if (this.salesChannel && !this.domain) {
                    this.salesChannel.defaultMeasurementSystemId = measurementSystemId;
                    return;
                }

                this.domain.measurementSystemId = measurementSystemId;
            },

            get() {
                if (this.domain) {
                    return this.domain.measurementSystemId ?? this.salesChannel.defaultMeasurementSystemId;
                }

                if (this.salesChannel && this.salesChannel._isNew) {
                    this.salesChannel.defaultMeasurementSystemId = this.measurementSystemConfig?.['core.measurementSystem.typeId'];
                }

                return this.salesChannel.defaultMeasurementSystemId;
            }
        },

        lengthUnitId: {
            get() {
                if (this.domain) {
                    return this.domain.lengthUnitId ?? this.salesChannel.defaultLengthUnitId;
                }

                if (this.salesChannel && this.salesChannel._isNew) {
                    this.salesChannel.defaultLengthUnitId = this.measurementSystemConfig?.['core.measurementSystem.lengthUnitId'];
                }

                return this.salesChannel.defaultLengthUnitId
            },

            set(lengthUnitId) {
                if (this.salesChannel && !this.domain) {
                    this.salesChannel.defaultLengthUnitId = lengthUnitId;
                    return;
                }

                this.domain.lengthUnitId = lengthUnitId;
            }
        },

        massUnitId: {
            set(massUnitId) {
                if (this.salesChannel && !this.domain) {
                    this.salesChannel.defaultMassUnitId = massUnitId;
                    return;
                }

                this.domain.massUnitId = massUnitId;
            },

            get() {
                if (this.domain) {
                    return this.domain.massUnitId ?? this.salesChannel.defaultMassUnitId;
                }

                if (this.salesChannel && this.salesChannel._isNew) {
                    this.salesChannel.defaultMassUnitId = this.measurementSystemConfig?.['core.measurementSystem.massUnitId'];
                }

                return this.salesChannel.defaultMassUnitId
            }
        }
    },

    async created() {
        await this.getMeasurementSystemConfig();

        this.defaultMeasurment = {
            measurementSystemId: this.measurementSystemId,
            lengthUnitId: this.lengthUnitId,
            massUnitId: this.massUnitId,
        }
    },

    methods: {
        onMeasurementSystemChange(measurementSystemId) {
            const measurementSystemSelect = (
                this.$refs.measurementSystemSelect as { results: EntityCollection<'measurement_system'> }
            );

            const measurementSystem = measurementSystemSelect.results.get(measurementSystemId);
            this.$emit(
                'measurement-system-change',
                measurementSystemId,
                measurementSystem,
            );

            if (measurementSystemId === this.defaultMeasurment.measurementSystemId) {
                this.lengthUnitId = this.defaultMeasurment.lengthUnitId;
                this.massUnitId = this.defaultMeasurment.massUnitId;

                return;
            }

            this.lengthUnitId = measurementSystem?.units?.filter(
                (unit): Entity<'measurement_display_unit'> => unit.type === 'length'
            ).first()?.id;

            this.massUnitId = measurementSystem?.units?.filter(
                (unit): Entity<'measurement_display_unit'> => unit.type === 'mass'
            ).first()?.id;
        },

        async getMeasurementSystemConfig() {
            this.measurementSystemConfig = await this.systemConfigApiService.getValues('core.measurementSystem');
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
