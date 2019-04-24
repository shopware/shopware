import { Component, Mixin, State } from 'src/core/shopware';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-sales-channel-detail.html.twig';

Component.register('sw-sales-channel-detail', {

    template,

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder'),
        Mixin.getByName('discard-detail-page-changes')('salesChannel')
    ],

    data() {
        return {
            salesChannel: {},
            isLoading: false,
            attributeSets: []
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier)
        };
    },

    computed: {
        identifier() {
            return this.placeholder(this.salesChannel, 'name');
        },
        salesChannelStore() {
            return State.getStore('sales_channel');
        },
        salesChannelLanguagesStore() {
            return this.salesChannel.getAssociation('languages');
        },
        salesChannelCurrenciesStore() {
            return this.salesChannel.getAssociation('currencies');
        },
        isStoreFront() {
            return this.salesChannel.typeId === '8a243080f92e4c719546314b577cf82b';
        },
        attributeSetStore() {
            return State.getStore('attribute_set');
        }
    },

    created() {
        this.createdComponent();
    },

    watch: {
        '$route.params.id'() {
            this.createdComponent();
        }
    },

    methods: {
        createdComponent() {
            if (!this.$route.params.id) {
                return;
            }

            this.loadEntityData();
        },

        loadEntityData() {
            this.salesChannel = this.salesChannelStore.getById(this.$route.params.id);

            this.attributeSetStore.getList({
                page: 1,
                limit: 100,
                criteria: CriteriaFactory.equals('relations.entityName', 'sales_channel'),
                associations: {
                    attributes: {
                        limit: 100,
                        sort: 'attribute.config.attributePosition'
                    }
                }
            }, true).then((response) => {
                this.attributeSets = response.items;
            });
        },

        onSave() {
            const titleSaveSuccess = this.$tc('sw-sales-channel.detail.titleSaveSuccess');
            const messageSaveSuccess = this.$tc(
                'sw-sales-channel.detail.messageSaveSuccess',
                0,
                { name: this.salesChannel.name }
            );

            this.syncWithDomains();

            return this.salesChannel.save().then(() => {
                this.$root.$emit('changed-sales-channel');
                this.createNotificationSuccess({
                    title: titleSaveSuccess,
                    message: messageSaveSuccess
                });
            });
        },

        abortOnLanguageChange() {
            return this.salesChannel.hasChanges();
        },

        saveOnLanguageChange() {
            return this.onSave();
        },

        onChangeLanguage() {
            this.loadEntityData();
        },

        /**
         * For storefront sales channels, the possible languages and currencies are determined by those in the domains
         * instead of salesChannel.`languages`/`currencies`. Theses mappings are still required by the backend, so we
         * need to add the missing ones, that are only set in the domains.
         */
        syncWithDomains() {
            if (!this.next1387 || !this.isStoreFront || !this.salesChannel.domains) {
                return;
            }
            this.salesChannel.domains.forEach((domain) => {
                if (!this.salesChannel.languages.find(d => d.languageId === domain.languageId)) {
                    const language = this.salesChannelLanguagesStore.create(domain.languageId);
                    this.salesChannel.languages.push(language);
                    if (!this.salesChannel.languageId) {
                        this.salesChannel.languageId = language.id;
                    }
                }

                if (!this.salesChannel.currencies.find(d => d.currencyId === domain.currencyId)) {
                    const currency = this.salesChannelCurrenciesStore.create(domain.currencyId);
                    this.salesChannel.currencies.push(currency);
                    if (!this.salesChannel.currencyId) {
                        this.salesChannel.currencyId = currency.id;
                    }
                }
            });
        }
    }
});
