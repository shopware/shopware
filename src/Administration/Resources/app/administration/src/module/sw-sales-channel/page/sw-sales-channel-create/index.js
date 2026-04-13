/**
 * @sw-package discovery
 */

import template from './sw-sales-channel-create.html.twig';

const { Context } = Shopware;
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

    beforeRouteEnter: insertIdIntoRoute,

    beforeRouteUpdate: insertIdIntoRoute,

    inject: ['systemConfigApiService'],

    computed: {
        allowSaving() {
            return this.acl.can('sales_channel.creator');
        },
    },

    methods: {
        createdComponent() {
            if (!this.$route.params.typeId) {
                return;
            }

            if (!Shopware.Store.get('context').isSystemDefaultLanguage) {
                Shopware.Store.get('context').resetLanguageToDefault();
            }

            this.salesChannel = this.salesChannelRepository.create();
            this.salesChannel.typeId = this.$route.params.typeId;
            this.salesChannel.active = false;

            // Set default language from admin context
            const defaultLanguageId = Shopware.Store.get('context').api.languageId;
            this.salesChannel.languageId = defaultLanguageId;
            this.ensureDefaultLanguageInCollection(defaultLanguageId);

            this.setMeasurementUnits()
                .catch(() => {
                    this.createNotificationError({
                        message: this.$tc('sw-sales-channel.detail.messageMeasurementUnitsSetError'),
                    });
                })
                .finally(() => {
                    this.$super('createdComponent');
                });
        },

        async setMeasurementUnits() {
            const measurementUnits = await this.getMeasurementUnits();

            this.salesChannel.measurementUnits = {
                system: measurementUnits['core.measurementUnits.system'],
                units: {
                    length: measurementUnits['core.measurementUnits.length'],
                    weight: measurementUnits['core.measurementUnits.weight'],
                },
            };
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

        getMeasurementUnits() {
            return this.systemConfigApiService.getValues('core.measurementUnits');
        },

        ensureDefaultLanguageInCollection(languageId) {
            if (!languageId || !this.salesChannel?.languages) {
                return;
            }

            if (this.salesChannel.languages.has(languageId)) {
                return;
            }

            const languageRepository = this.repositoryFactory.create('language');
            languageRepository.get(languageId, Context.api).then((language) => {
                if (!language || this.salesChannel.languages.has(languageId)) {
                    return;
                }

                if (typeof this.salesChannel.languages.add === 'function') {
                    this.salesChannel.languages.add(language);
                    return;
                }

                this.salesChannel.languages = this.salesChannel.languages.concat([language]);
            });
        },
    },
};
