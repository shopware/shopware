import template from './sw-customer-group-registration-seo-modal.html.twig';

const { Component, Mixin } = Shopware;
const { Criteria, EntityCollection } = Shopware.Data;

Component.register('sw-customer-group-registration-seo-modal', {
    template,
    inject: ['repositoryFactory', 'seoUrlService'],

    mixins: [
        Mixin.getByName('notification')
    ],

    props: {
        customerGroup: {
            type: Object,
            required: true
        },
        seoUrls: {
            type: Array,
            required: true
        }
    },

    data() {
        return {
            isLoading: false,
            urlSuffix: '',
            salesChannels: new EntityCollection('test', 'sales_channel', Shopware.Context.api)
        };
    },

    computed: {
        seoUrlRepository() {
            return this.repositoryFactory.create('seo_url');
        },

        exampleUrl() {
            let domainUrl = 'https://www.shopdomain.com/saleschannel';

            this.salesChannels.forEach(salesChannel => {
                salesChannel.domains.forEach(domain => {
                    domainUrl = domain.url;
                });
            });

            return `${domainUrl}/${this.urlSuffix}`;
        },

        canBeGenerated() {
            return this.salesChannels.length && this.urlSuffix.length;
        },

        salesChannelCriteria() {
            const criteria = new Criteria();
            criteria.addAssociation('domains');

            return criteria;
        }
    },

    methods: {
        cancel() {
            this.$emit('close');
        },

        async generate() {
            this.isLoading = true;
            const urls = [];
            const salesChannelIds = [];
            const languageIds = [];

            this.salesChannels.forEach(salesChannel => {
                salesChannelIds.push(salesChannel.id);

                salesChannel.domains.forEach(domain => {
                    languageIds.push(domain.languageId);
                });

                const entity = this.seoUrlRepository.create(Shopware.Context.api);
                entity.salesChannelId = salesChannel.id;
                entity.foreignKey = this.customerGroup.id;
                entity.routeName = 'frontend.account.customer-group-registration.page';
                entity.pathInfo = `/customer-group-registration/${this.customerGroup.id}`;
                entity.seoPathInfo = this.urlSuffix;
                entity.isCanonical = true;

                urls.push(entity);
            });

            this.seoUrls.forEach((url) => {
                if (!salesChannelIds.includes(url.salesChannelId)) {
                    this.seoUrlRepository.delete(url.id, Shopware.Context.api);
                }
            });

            try {
                const saves = [];

                languageIds.forEach(id => {
                    saves.push(this.seoUrlService.createCustomUrl('frontend.account.customer-group-registration.page', urls, {}, { 'sw-language-id': id }));
                });

                await Promise.all(saves);

                this.createNotificationSuccess({
                    title: this.$tc('global.default.success'),
                    message: this.$tc('sw-settings-customer-group.notification.successfulGeneratedSeoUrls')
                });
            } catch (e) {
                this.createNotificationError({
                    title: this.$tc('global.default.error'),
                    message: this.$tc('sw-settings-customer-group.notification.errorUnableToCreateSeoUrls')
                });
                return;
            }

            this.isLoading = false;
            this.$emit('close');
            this.$emit('refreshSeoUrls');
        }
    }
});
