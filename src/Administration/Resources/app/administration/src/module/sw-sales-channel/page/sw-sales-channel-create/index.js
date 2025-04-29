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

    inject: ['systemConfigApiService'],

    beforeRouteEnter: insertIdIntoRoute,

    beforeRouteUpdate: insertIdIntoRoute,

    data() {
        return {
            measurementSystemConfig: [],
        }
    },

    computed: {
        allowSaving() {
            return this.acl.can('sales_channel.creator');
        },
    },

    methods: {
        async createdComponent() {
            if (!this.$route.params.typeId) {
                return;
            }

            if (!Shopware.Store.get('context').isSystemDefaultLanguage) {
                Shopware.Store.get('context').resetLanguageToDefault();
            }

            await this.getMeasurementSystemConfig();

            this.salesChannel = this.salesChannelRepository.create();
            this.salesChannel.typeId = this.$route.params.typeId;
            this.salesChannel.active = false;

            this.salesChannel.measurementSystemId = this.measurementSystemConfig['core.measurementSystem.typeId'];
            this.salesChannel.lengthUnitId = this.measurementSystemConfig['core.measurementSystem.lengthUnitId'];
            this.salesChannel.massUnitId = this.measurementSystemConfig['core.measurementSystem.massUnitId'];

            this.$super('createdComponent');
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

        async getMeasurementSystemConfig() {
            this.measurementSystemConfig = await this.systemConfigApiService.getValues('core.measurementSystem');
        },
    },
};
