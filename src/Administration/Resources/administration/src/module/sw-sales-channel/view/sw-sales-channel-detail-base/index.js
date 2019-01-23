import { Component, Mixin, State } from 'src/core/shopware';
import template from './sw-sales-channel-detail-base.html.twig';
import './sw-sales-channel-detail-base.less';

Component.register('sw-sales-channel-detail-base', {
    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    inject: [
        'salesChannelService'
    ],

    props: {
        salesChannel: {
            type: Object,
            required: true,
            default: {}
        }
    },

    watch: {
        '$route.params.id'() {
            this.getDomains();
        }
    },

    data() {
        return {
            showDeleteModal: false,
            defaultSnippetSetId: '71a916e745114d72abafbfdc51cbd9d0',
            isLoadingDomains: false,
            deleteDomain: null
        };
    },

    computed: {
        secretAccessKeyFieldType() {
            return this.showSecretAccessKey ? 'text' : 'password';
        },

        catalogStore() {
            return State.getStore('catalog');
        },

        catalogAssociationStore() {
            return this.salesChannel.getAssociation('catalogs');
        },

        countryStore() {
            return State.getStore('country');
        },

        countryAssociationStore() {
            return this.salesChannel.getAssociation('countries');
        },

        currencyStore() {
            return State.getStore('currency');
        },

        currencyAssociationStore() {
            return this.salesChannel.getAssociation('currencies');
        },

        languageStore() {
            return State.getStore('language');
        },

        languageAssociationStore() {
            return this.salesChannel.getAssociation('languages');
        },

        paymentMethodStore() {
            return State.getStore('payment_method');
        },

        paymentMethodAssociationStore() {
            return this.salesChannel.getAssociation('paymentMethods');
        },

        shippingMethodStore() {
            return State.getStore('shipping_method');
        },

        shippingMethodAssociationStore() {
            return this.salesChannel.getAssociation('shippingMethods');
        },
        domainAssociationStore() {
            return this.salesChannel.getAssociation('domains');
        },
        snippetSetStore() {
            return State.getStore('snippet_set');
        },
        isStoreFront() {
            return this.salesChannel.typeId === '8a243080f92e4c719546314b577cf82b';
        }
    },

    created() {
        this.getDomains();
    },

    methods: {
        getDomains() {
            this.isLoadingDomains = true;

            this.domainAssociationStore.getList({}, true).then(() => {
                this.isLoadingDomains = false;
            });
        },

        onGenerateKeys() {
            this.salesChannelService.generateKey().then((response) => {
                this.salesChannel.accessKey = response.accessKey;
            }).catch(() => {
                this.createNotificationError({
                    title: this.$tc('sw-sales-channel.detail.titleAPIError'),
                    message: this.$tc('sw-sales-channel.detail.messageAPIError')
                });
            });
        },

        changeDefaultCurrency(id) {
            this.salesChannel.currencyId = id;
        },

        changeDefaultLanguage(id) {
            this.salesChannel.languageId = id;
        },

        changeDefaultCountry(id) {
            this.salesChannel.countryId = id;
        },

        changeDefaultPaymentMethod(id) {
            this.salesChannel.paymentMethodId = id;
        },

        changeDefaultShippingMethod(id) {
            this.salesChannel.shippingMethodId = id;
        },

        onCloseDeleteModal() {
            this.showDeleteModal = false;
        },

        onConfirmDelete() {
            this.showDeleteModal = false;
            this.$nextTick(() => {
                this.salesChannel.delete(true).then(() => {
                    this.$root.$emit('changedSalesChannels');
                });

                this.$router.push({ name: 'sw.dashboard.index' });
            });
        },
        onClickAddDomain() {
            const newDomain = this.domainAssociationStore.create();
            if (!this.next717) {
                newDomain.snippetSetId = this.defaultSnippetSetId;
            }
            this.salesChannel.domains.push(newDomain);
        },
        onClickDeleteDomain(domain) {
            if (domain.isLocal) {
                this.onConfirmDeleteDomain(domain);
            } else {
                this.deleteDomain = domain;
            }
        },
        onConfirmDeleteDomain(domain) {
            this.deleteDomain = null;
            this.$nextTick(() => {
                domain.delete(true).then(() => {
                    this.salesChannel.domains = this.salesChannel.domains.filter((x) => {
                        return x.id !== domain.id;
                    });
                });
            });
        },
        onCloseDeleteDomainModal() {
            this.deleteDomain = null;
        }
    }
});
