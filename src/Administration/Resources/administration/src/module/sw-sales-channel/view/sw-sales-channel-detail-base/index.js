import { mapApiErrors } from 'src/app/service/map-errors.service';
import template from './sw-sales-channel-detail-base.html.twig';
import './sw-sales-channel-detail-base.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-sales-channel-detail-base', {
    template,

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder')
    ],

    inject: [
        'salesChannelService',
        'productExportService',
        'repositoryFactory',
        'context'
    ],

    props: {
        salesChannel: {
            required: true
        },

        productExport: {
            required: true
        },

        storefrontSalesChannelCriteria: {
            type: Criteria,
            required: false
        },

        customFieldSets: {
            type: Array,
            required: true
        },

        isLoading: {
            type: Boolean,
            default: false
        }
    },

    data() {
        return {
            showDeleteModal: false,
            defaultSnippetSetId: '71a916e745114d72abafbfdc51cbd9d0',
            isLoadingDomains: false,
            deleteDomain: null,
            storefrontSalesChannels: [],
            storefrontDomains: [],
            selectedStorefrontSalesChannel: null
        };
    },

    computed: {
        secretAccessKeyFieldType() {
            return this.showSecretAccessKey ? 'text' : 'password';
        },

        isStoreFront() {
            return this.salesChannel.typeId === '8a243080f92e4c719546314b577cf82b';
        },

        isProductComparison() {
            return this.salesChannel.typeId === 'ed535e5722134ac1aa6524f73e26881b';
        },

        storefrontSalesChannelDomainCriteria() {
            const criteria = new Criteria();

            return criteria.addFilter(Criteria.equals('salesChannelId', this.selectedStorefrontSalesChannelId));
        },

        storefrontDomainsLoaded() {
            return this.storefrontDomains.length > 0;
        },

        selectedStorefrontSalesChannelId() {
            if (this.productExport && this.productExport.salesChannelDomain) {
                return this.productExport.salesChannelDomain.salesChannelId;
            }
            if (this.selectedStorefrontSalesChannel) {
                return this.selectedStorefrontSalesChannel.id;
            }

            return null;
        },

        domainRepository() {
            return this.repositoryFactory.create(
                this.salesChannel.domains.entity,
                this.salesChannel.domains.source
            );
        },

        globalDomainRepository() {
            return this.repositoryFactory.create('sales_channel_domain');
        },

        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },

        mainNavigationCriteria() {
            const criteria = new Criteria(1, 10);

            return criteria.addFilter(Criteria.equals('type', 'page'));
        },

        getIntervalOptions() {
            return [
                {
                    id: 0,
                    name: this.$tc('sw-sales-channel.detail.product-comparison.interval-labels.0')
                },
                {
                    id: 120,
                    name: this.$tc('sw-sales-channel.detail.product-comparison.interval-labels.120')
                },
                {
                    id: 300,
                    name: this.$tc('sw-sales-channel.detail.product-comparison.interval-labels.300')
                },
                {
                    id: 600,
                    name: this.$tc('sw-sales-channel.detail.product-comparison.interval-labels.600')
                },
                {
                    id: 900,
                    name: this.$tc('sw-sales-channel.detail.product-comparison.interval-labels.900')
                },
                {
                    id: 1800,
                    name: this.$tc('sw-sales-channel.detail.product-comparison.interval-labels.1800')
                },
                {
                    id: 3600,
                    name: this.$tc('sw-sales-channel.detail.product-comparison.interval-labels.3600')
                },
                {
                    id: 7200,
                    name: this.$tc('sw-sales-channel.detail.product-comparison.interval-labels.7200')
                },
                {
                    id: 14400,
                    name: this.$tc('sw-sales-channel.detail.product-comparison.interval-labels.14400')
                },
                {
                    id: 28800,
                    name: this.$tc('sw-sales-channel.detail.product-comparison.interval-labels.28800')
                },
                {
                    id: 43200,
                    name: this.$tc('sw-sales-channel.detail.product-comparison.interval-labels.43200')
                },
                {
                    id: 86400,
                    name: this.$tc('sw-sales-channel.detail.product-comparison.interval-labels.86400')
                },
                {
                    id: 172800,
                    name: this.$tc('sw-sales-channel.detail.product-comparison.interval-labels.172800')
                }
            ];
        },

        getFileFormatOptions() {
            return [
                {
                    id: 'csv',
                    name: this.$tc('sw-sales-channel.detail.product-comparison.file-format-labels.csv')
                },
                {
                    id: 'xml',
                    name: this.$tc('sw-sales-channel.detail.product-comparison.file-format-labels.xml')
                }
            ];
        },

        getEncodingOptions() {
            return [
                {
                    id: 'ISO-8859-1',
                    name: 'ISO-8859-1'
                },
                {
                    id: 'UTF-8',
                    name: 'UTF-8'
                }
            ];
        },

        ...mapApiErrors('salesChannel',
            [
                'paymentMethodId',
                'shippingMethodId',
                'countryId',
                'currencyId',
                'languageId',
                'customerGroupId',
                'navigationCategoryId'
            ])
    },

    methods: {
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

        onGenerateProductExportKey() {
            this.productExportService.generateKey().then((response) => {
                this.productExport.accessKey = response.accessKey;
            }).catch(() => {
                this.createNotificationError({
                    title: this.$tc('sw-sales-channel.detail.titleAPIError'),
                    message: this.$tc('sw-sales-channel.detail.messageAPIError')
                });
            });
        },

        onDefaultItemAdd(item, ref, property) {
            if (!this.salesChannel[property].has(item.id)) {
                this.salesChannel[property].push(item);
            }
        },

        onRemoveItem(item, ref, property) {
            const defaultSelection = this.$refs[ref].singleSelection;
            if (defaultSelection !== null && item.id === defaultSelection.id) {
                this.salesChannel[property] = null;
            }
        },

        onToggleActive() {
            if (this.salesChannel.active !== true) {
                return;
            }
            const criteria = new Criteria();
            criteria.addAssociation('themes');
            this.salesChannelRepository
                .get(this.$route.params.id, this.context, criteria)
                .then((entity) => {
                    if (entity.extensions.themes !== undefined && entity.extensions.themes.length >= 1) {
                        return;
                    }

                    this.salesChannel.active = false;
                    this.createNotificationError({
                        title: this.$tc('sw-sales-channel.detail.titleActivateError'),
                        message: this.$tc('sw-sales-channel.detail.messageActivateWithoutThemeError', 0, {
                            name: this.salesChannel.name || this.placeholder(this.salesChannel, 'name')
                        })
                    });
                });
        },

        onCloseDeleteModal() {
            this.showDeleteModal = false;
        },

        onConfirmDelete() {
            this.showDeleteModal = false;

            this.$nextTick(() => {
                this.deleteSalesChannel(this.salesChannel.id);
                this.$router.push({ name: 'sw.dashboard.index' });
            });
        },

        deleteSalesChannel(salesChannelId) {
            this.salesChannelRepository.delete(salesChannelId, this.context).then(() => {
                this.$root.$emit('sales-channel-change');
            });
        },

        onClickAddDomain() {
            const newDomain = this.domainRepository.create(this.context);
            newDomain.snippetSetId = this.defaultSnippetSetId;

            this.salesChannel.domains.add(newDomain);
        },

        onClickDeleteDomain(domain) {
            if (domain.isNew()) {
                this.onConfirmDeleteDomain(domain);
            } else {
                this.deleteDomain = domain;
            }
        },

        onConfirmDeleteDomain(domain) {
            this.deleteDomain = null;

            this.$nextTick(() => {
                this.salesChannel.domains.remove(domain.id);

                if (domain.isNew()) {
                    return;
                }

                this.domainRepository.delete(domain.id, this.context);
            });
        },

        onCloseDeleteDomainModal() {
            this.deleteDomain = null;
        },

        onStorefrontSelectionChange(storefrontSalesChannelId) {
            this.selectedStorefrontSalesChannelId = storefrontSalesChannelId;

            this.salesChannelRepository
                .get(storefrontSalesChannelId, this.context)
                .then((entity) => {
                    this.selectedStorefrontSalesChannel = entity;
                    this.selectedStorefrontSalesChannelId = entity.id;

                    this.salesChannel.languageId = entity.languageId;
                    this.salesChannel.currencyId = entity.currencyId;
                    this.salesChannel.paymentMethodId = entity.paymentMethodId;
                    this.salesChannel.shippingMethodId = entity.shippingMethodId;
                    this.salesChannel.countryId = entity.countryId;
                    this.salesChannel.navigationCategoryId = entity.navigationCategoryId;
                    this.salesChannel.navigationCategoryVersionId = entity.navigationCategoryVersionId;
                    this.salesChannel.customerGroupId = entity.customerGroupId;
                });
        },

        loadStorefrontSalesChannels() {
            const criteria = new Criteria();

            criteria.addFilter(Criteria.equals('typeId', '8a243080f92e4c719546314b577cf82b'));

            this.isLoading = true;
            this.salesChannelRepository
                .search(criteria, this.context)
                .then((searchResult) => {
                    this.storefrontSalesChannels = searchResult;
                    this.isLoading = false;
                });
        },

        loadStorefrontDomains(storefrontSalesChannelId) {
            const criteria = new Criteria();

            criteria.addFilter(Criteria.equals('salesChannelId', storefrontSalesChannelId));

            this.globalDomainRepository
                .search(criteria, this.context)
                .then((searchResult) => {
                    this.storefrontDomains = searchResult;
                });
        }
    }
});
