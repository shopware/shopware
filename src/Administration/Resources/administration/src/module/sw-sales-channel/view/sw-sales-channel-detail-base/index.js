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
        'repositoryFactory',
        'apiContext'
    ],

    props: {
        salesChannel: {
            required: true
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
            deleteDomain: null
        };
    },

    computed: {
        secretAccessKeyFieldType() {
            return this.showSecretAccessKey ? 'text' : 'password';
        },

        isStoreFront() {
            return this.salesChannel.typeId === '8a243080f92e4c719546314b577cf82b';
        },

        domainRepository() {
            return this.repositoryFactory.create(
                this.salesChannel.domains.entity,
                this.salesChannel.domains.source
            );
        },

        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },

        mainNavigationCriteria() {
            const criteria = new Criteria(1, 10);

            return criteria.addFilter(Criteria.equals('type', 'page'));
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
                .get(this.$route.params.id, this.apiContext, criteria)
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
            this.salesChannelRepository.delete(salesChannelId, this.apiContext).then(() => {
                this.$root.$emit('sales-channel-change');
            });
        },

        onClickAddDomain() {
            const newDomain = this.domainRepository.create(this.apiContext);
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

                this.domainRepository.delete(domain.id, this.apiContext);
            });
        },

        onCloseDeleteDomainModal() {
            this.deleteDomain = null;
        }
    }
});
