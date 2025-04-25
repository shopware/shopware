/**
 * @sw-package discovery
 */

import template from './sw-sales-channel-create.html.twig';

const utils = Shopware.Utils;

const insertIdIntoRoute = (to, from, next) => {
    if (to.name.includes('sw.sales.channel.create') && !to.params.id) {
        to.params.id = utils.createId();
    }

    next();
};

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'systemConfigApiService',
    ],

    beforeRouteEnter: insertIdIntoRoute,

    beforeRouteUpdate: insertIdIntoRoute,

    data() {
        return {
            measurementSystemConfig: null,
        };
    },


    computed: {
        allowSaving() {
            return this.acl.can('sales_channel.creator');
        },
    },


    watch: {
        'salesChannel.defaultMeasurementSystemId': {
            handler(value) {
                if (value !== this.measurementSystemConfig['core.measurementSystem.typeId']) {
                    this.salesChannel.defaultLengthUnitId = null;
                    this.salesChannel.defaultMassUnitId = null;
                    return;
                }

                this.salesChannel.defaultLengthUnitId = this.measurementSystemConfig['core.measurementSystem.lengthUnitId'];
                this.salesChannel.defaultMassUnitId = this.measurementSystemConfig['core.measurementSystem.massUnitId'];
            },
            deep: true,
        },
    },


    methods: {
        async createdComponent() {
            if (!this.$route.params.typeId) {
                return;
            }

            await this.getMeasurementSystemConfig();

            if (!Shopware.Store.get('context').isSystemDefaultLanguage) {
                Shopware.Store.get('context').resetLanguageToDefault();
            }

            this.salesChannel = this.salesChannelRepository.create();
            this.salesChannel.typeId = this.$route.params.typeId;
            this.salesChannel.active = false;
            this.salesChannel.defaultMeasurementSystemId = this.measurementSystemConfig['core.measurementSystem.typeId'];
            this.salesChannel.defaultLengthUnitId = this.measurementSystemConfig['core.measurementSystem.lengthUnitId'];
            this.salesChannel.defaultMassUnitId = this.measurementSystemConfig['core.measurementSystem.massUnitId'];

            this.$super('createdComponent');
        },

        async getMeasurementSystemConfig() {
            this.measurementSystemConfig = await this.systemConfigApiService.getValues('core.measurementSystem');
        },

        saveFinish() {
            this.isSaveSuccessful = false;
            this.$router.push({
                name: 'sw.sales.channel.detail',
                params: { id: this.salesChannel.id },
            });
        },

        onSave() {
            this.$super('onSave');
        },
    },
};
