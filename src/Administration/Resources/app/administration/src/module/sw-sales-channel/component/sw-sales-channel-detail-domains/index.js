import template from './sw-sales-channel-detail-domains.html.twig';

const { Component, Context } = Shopware;

Component.register('sw-sales-channel-detail-domains', {
    template,

    inject: [
        'repositoryFactory'
    ],

    props: {
        salesChannel: {
            required: true
        }
    },

    computed: {
        domainRepository() {
            return this.repositoryFactory.create(
                this.salesChannel.domains.entity,
                this.salesChannel.domains.source
            );
        },

        currentDomainModalTitle() {
            if (this.currentDomain.url && this.currentDomain.url.length) {
                return this.$t('sw-sales-channel.detail.titleEditDomain', 0, { name: this.currentDomain.url });
            }
            return this.$t('sw-sales-channel.detail.titleCreateDomain');
        },

        currentDomainModalButtonText() {
            if (this.currentDomain.url && this.currentDomain.url.length) {
                return this.$t('sw-sales-channel.detail.buttonEditDomain');
            }
            return this.$t('sw-sales-channel.detail.buttonAddDomain');
        }
    },

    data() {
        return {
            salesChannelDomains: [],
            currentDomain: null,
            isLoadingDomains: false,
            deleteDomain: null
        };
    },

    methods: {
        onClickOpenCreateDomainModal() {
            const currentDomain = this.domainRepository.create(Context.api);

            currentDomain.snippetSetId = this.defaultSnippetSetId;
            this.currentDomain = currentDomain;
        },

        onClickAddNewDomain() {
            if (this.currentDomain.isNew()) {
                this.salesChannel.domains.add(this.currentDomain);
            }
            this.onCloseCreateDomainModal();
        },

        onClickEditDomain(domain) {
            this.currentDomain = domain;
        },

        onCloseCreateDomainModal() {
            this.currentDomain = null;
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

                this.domainRepository.delete(domain.id, Context.api);
            });
        },

        onCloseDeleteDomainModal() {
            this.deleteDomain = null;
        },

        getDomainColumns() {
            return [{
                property: 'url',
                dataIndex: 'url',
                label: 'URL',
                allowResize: false,
                primary: true,
                inlineEdit: true
            }, {
                property: 'languageId',
                dataIndex: 'languageId',
                label: 'Language',
                allowResize: false,
                inlineEdit: false
            }, {
                property: 'snippetSetId',
                dataIndex: 'snippetSetId',
                label: 'Snippet',
                allowResize: false,
                inlineEdit: false
            }, {
                property: 'currencyId',
                dataIndex: 'currencyId',
                label: 'Currency',
                allowResize: false,
                inlineEdit: false
            }];
        }
    }
});
