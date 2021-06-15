import template from './sw-sales-channel-detail-domains.html.twig';
import './sw-sales-channel-detail-domains.scss';

const { Component, Context } = Shopware;
const { Criteria } = Shopware.Data;


Component.register('sw-sales-channel-detail-domains', {
    template,

    inject: [
        'repositoryFactory',
    ],

    props: {
        // FIXME: add type to salesChannel property
        // eslint-disable-next-line vue/require-prop-types
        salesChannel: {
            required: true,
        },

        disableEdit: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            currentDomain: null,
            currentDomainBackup: {
                url: null,
                language: null,
                languageId: null,
                currency: null,
                currencyId: null,
                snippetSet: null,
                snippetSetId: null,
            },
            isLoadingDomains: false,
            deleteDomain: null,
        };
    },

    computed: {
        domainRepository() {
            return this.repositoryFactory.create(
                this.salesChannel.domains.entity,
                this.salesChannel.domains.source,
            );
        },

        currentDomainModalTitle() {
            if (this.currentDomain.isNew()) {
                return this.$t('sw-sales-channel.detail.titleCreateDomain');
            }

            return this.$t('sw-sales-channel.detail.titleEditDomain', 0, {
                name: this.$options.filters.unicodeUri(this.currentDomainBackup.url),
            });
        },

        currentDomainModalButtonText() {
            if (this.currentDomain.isNew()) {
                return this.$t('sw-sales-channel.detail.buttonAddDomain');
            }
            return this.$t('sw-sales-channel.detail.buttonEditDomain');
        },

        snippetSetCriteria() {
            return (new Criteria())
                .addSorting(Criteria.sort('name', 'ASC'));
        },

        salesChannelFilterCriteria() {
            const criteria = new Criteria();

            criteria
                .addAssociation('salesChannels')
                .addSorting(Criteria.sort('name', 'ASC'));

            return criteria.addFilter(Criteria.equals('salesChannels.id', this.salesChannel.id));
        },

        currencyCriteria() {
            return (new Criteria())
                .addSorting(Criteria.sort('name', 'ASC'));
        },

        hreflangLocalisationOptions() {
            return [
                {
                    name: this.$tc('sw-sales-channel.detail.hreflang.domainSettings.byIso'),
                    value: false,
                    helpText: this.$tc('sw-sales-channel.detail.hreflang.domainSettings.byIsoHelpText'),
                },
                {
                    name: this.$tc('sw-sales-channel.detail.hreflang.domainSettings.byAbbreviation'),
                    value: true,
                    helpText: this.$tc('sw-sales-channel.detail.hreflang.domainSettings.byAbbreviationHelpText'),
                },
            ];
        },

        disabled() {
            return !this.currentDomain ||
                !this.currentDomain.currencyId ||
                !this.currentDomain.snippetSetId ||
                !this.currentDomain.url ||
                !this.currentDomain.languageId ||
                this.disableEdit;
        },
    },

    methods: {
        setCurrentDomainBackup(domain) {
            this.currentDomainBackup = {
                url: domain.url,
                language: domain.language,
                languageId: domain.languageId,
                currency: domain.currency,
                currencyId: domain.currencyId,
                snippetSet: domain.snippetSet,
                snippetSetId: domain.snippetSetId,
            };
        },

        resetCurrentDomainToBackup() {
            this.currentDomain.url = this.currentDomainBackup.url;
            this.currentDomain.language = this.currentDomainBackup.language;
            this.currentDomain.languageId = this.currentDomainBackup.languageId;
            this.currentDomain.currency = this.currentDomainBackup.currency;
            this.currentDomain.currencyId = this.currentDomainBackup.currencyId;
            this.currentDomain.snippetSet = this.currentDomainBackup.snippetSet;
            this.currentDomain.snippetSetId = this.currentDomainBackup.snippetSetId;
        },

        onClickOpenCreateDomainModal() {
            this.currentDomain = this.domainRepository.create(Context.api);
            this.setCurrentDomainBackup(this.currentDomain);
        },

        onClickAddNewDomain() {
            const currentDomainId = this.currentDomain.id;

            if (this.currentDomain.isNew() && !this.salesChannel.domains.has(currentDomainId)) {
                this.salesChannel.domains.add(this.currentDomain);
            }
            this.currentDomain = null;
        },

        onClickEditDomain(domain) {
            this.currentDomain = domain;
            this.setCurrentDomainBackup(this.currentDomain);
        },

        onCloseCreateDomainModal() {
            this.resetCurrentDomainToBackup();
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
            });
        },

        onCloseDeleteDomainModal() {
            this.deleteDomain = null;
        },

        onOptionSelect(name, entity) {
            this.currentDomain[name] = entity;
        },

        getDomainColumns() {
            return [{
                property: 'url',
                dataIndex: 'url',
                label: this.$t('sw-sales-channel.detail.columnDomainUrl'),
                allowResize: false,
                primary: true,
                inlineEdit: true,
            }, {
                property: 'languageId',
                dataIndex: 'languageId',
                label: this.$t('sw-sales-channel.detail.columnDomainLanguage'),
                allowResize: false,
                inlineEdit: false,
            }, {
                property: 'snippetSetId',
                dataIndex: 'snippetSetId',
                label: this.$t('sw-sales-channel.detail.columnDomainSnippetSet'),
                allowResize: false,
                inlineEdit: false,
            }, {
                property: 'currencyId',
                dataIndex: 'currencyId',
                label: this.$t('sw-sales-channel.detail.columnDomainCurrency'),
                allowResize: false,
                inlineEdit: false,
            }];
        },
    },
});
