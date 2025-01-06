/**
 * @package discovery
 */

import template from './sw-sales-channel-detail-domains.html.twig';
import './sw-sales-channel-detail-domains.scss';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { ShopwareError } = Shopware.Classes;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    compatConfig: Shopware.compatConfig,

    inject: [
        'repositoryFactory',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
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
            oldDomainUrl: null,
            isLoadingDomains: false,
            deleteDomain: null,
            sortBy: 'url',
            sortDirection: 'ASC',
            error: null,
            isEditingDomain: false,
        };
    },

    computed: {
        domainRepository() {
            return this.repositoryFactory.create(this.salesChannel.domains.entity, this.salesChannel.domains.source);
        },

        currentDomainModalTitle() {
            if (!this.isEditingDomain) {
                return this.$t('sw-sales-channel.detail.titleCreateDomain');
            }

            return this.$t('sw-sales-channel.detail.titleEditDomain', 0, {
                name: this.unicodeUriFilter(this.oldDomainUrl),
            });
        },

        currentDomainModalButtonText() {
            if (!this.isEditingDomain) {
                return this.$t('sw-sales-channel.detail.buttonAddDomain');
            }
            return this.$t('sw-sales-channel.detail.buttonEditDomain');
        },

        snippetSetCriteria() {
            return new Criteria(1, 25).addSorting(Criteria.sort('name', 'ASC'));
        },

        currencyCriteria() {
            return new Criteria(1, 25).addSorting(Criteria.sort('name', 'ASC'));
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
            return (
                !this.currentDomain ||
                !this.currentDomain.currencyId ||
                !this.currentDomain.snippetSetId ||
                !this.currentDomain.url ||
                !this.currentDomain.languageId ||
                this.disableEdit ||
                this.error !== null
            );
        },

        sortedDomains() {
            const domains = [...this.salesChannel.domains];

            return this.localSortDomains(domains);
        },
    },

    methods: {
        sortColumns(column) {
            if (this.sortBy === column.dataIndex) {
                // If the same column, that is already being sorted, is clicked again, change direction
                this.sortDirection = this.sortDirection === 'ASC' ? 'DESC' : 'ASC';
            } else {
                // We clicked on a new column to be sorted, therefore set the sort field and the direction to 'ASC'
                this.sortBy = column.dataIndex;
                this.sortDirection = 'ASC';
            }
        },

        unicodeUriFilter(uri) {
            const unicodeUriFilter = Shopware.Filter.getByName('unicodeUri');
            return unicodeUriFilter(uri);
        },

        localSortDomains(domains) {
            domains.sort((a, b) => {
                const valA = this.getSortValue(a, this.sortBy).toString();
                const valB = this.getSortValue(b, this.sortBy).toString();

                const compareVal = valA.localeCompare(valB, undefined, {
                    numeric: true,
                    sensitivity: 'base',
                });

                if (this.sortDirection === 'ASC') {
                    return compareVal;
                }

                return compareVal * -1;
            });

            return domains;
        },

        getSortValue(val, column) {
            // Removes 'Id' from fields like 'languageId', so we're accessing 'language' instead
            column = column.replace('Id', '');

            if (val.hasOwnProperty(column) && typeof val[column] === 'object' && val[column].hasOwnProperty('name')) {
                return val[column].name;
            }

            return val[column];
        },

        onInput() {
            this.error = null;
        },

        async verifyUrl(domain) {
            return !(this.domainExistsLocal(domain) || (await this.domainExistsInDatabase(domain.url)));
        },

        domainExistsLocal(currentDomain) {
            return (
                this.salesChannel.domains.filter(
                    (domain) => domain.id !== currentDomain.id && domain.url === currentDomain.url,
                ).length > 0
            );
        },

        async domainExistsInDatabase(url) {
            const globalDomainRepository = this.repositoryFactory.create(this.salesChannel.domains.entity);
            const criteria = new Criteria(1, 25);
            criteria.addFilter(Criteria.equals('url', url));

            const items = await globalDomainRepository.search(criteria);

            if (items.total === 0) {
                return false;
            }

            // Edge case: Delete domain, which is in database already, and then try to re-add it.
            // In that case a database entry is still available, yet locally it's not available anymore.
            // We don't want to prevent re-adding this domain in that case.
            return items.first().salesChannelId !== this.salesChannel.id;
        },

        setInitialCurrency(domain) {
            const currency = this.salesChannel.currencies.first();
            domain.currency = currency;
            domain.currencyId = currency.id;
        },

        setInitialLanguage(domain) {
            const language = this.salesChannel.languages.first();
            domain.language = language;
            domain.languageId = language.id;
        },

        onClickOpenCreateDomainModal() {
            const domain = this.domainRepository.create();

            if (this.salesChannel.currencies.length === 1) {
                this.setInitialCurrency(domain);
            }

            if (this.salesChannel.languages.length === 1) {
                this.setInitialLanguage(domain);
            }

            domain.hreflangUseOnlyLocale = false;

            this.currentDomain = domain;
            this.oldDomainUrl = null;
            this.isEditingDomain = false;
        },

        async onClickAddNewDomain() {
            const isNewDomain = !this.isEditingDomain;

            if (isNewDomain || this.currentDomain.url !== this.oldDomainUrl) {
                if (!(await this.verifyUrl(this.currentDomain))) {
                    this.error = new ShopwareError({
                        code: 'DUPLICATED_URL',
                    });

                    return;
                }
            }

            if (isNewDomain) {
                this.salesChannel.domains.add(this.currentDomain);
            }

            this.currentDomain = null;
            this.oldDomainUrl = null;
            this.isEditingDomain = false;
        },

        onClickEditDomain(domain) {
            this.currentDomain = domain;
            this.oldDomainUrl = this.currentDomain.url;
            this.isEditingDomain = true;
        },

        onCloseCreateDomainModal() {
            this.currentDomain = null;
            this.oldDomainUrl = null;
            this.isEditingDomain = false;
        },

        onClickDeleteDomain(domain) {
            if (domain.isNew()) {
                this.onConfirmDeleteDomain(domain);
            } else {
                this.deleteDomain = domain;
            }
        },

        onConfirmDeleteDomain(domain) {
            if (domain.productExports.length > 0) {
                this.createNotificationError({
                    message: this.$tc('sw-sales-channel.detail.messageDeleteDomainError', 0, {
                        url: this.unicodeUriFilter(domain.url),
                    }),
                });

                this.deleteDomain = null;

                return;
            }

            this.deleteDomain = null;

            this.$nextTick(() => {
                this.salesChannel.domains.remove(domain.id);
            });
        },

        onCloseDeleteDomainModal() {
            this.deleteDomain = null;
        },

        onLanguageSelect(id) {
            this.onOptionSelect('language', this.salesChannel.languages.get(id));
        },

        onCurrencySelect(id) {
            this.onOptionSelect('currency', this.salesChannel.currencies.get(id));
        },

        onOptionSelect(name, entity) {
            this.currentDomain[name] = entity;
        },

        getDomainColumns() {
            return [
                {
                    property: 'url',
                    dataIndex: 'url',
                    label: this.$t('sw-sales-channel.detail.columnDomainUrl'),
                    allowResize: false,
                    primary: true,
                    inlineEdit: true,
                },
                {
                    property: 'languageId',
                    dataIndex: 'languageId',
                    label: this.$t('sw-sales-channel.detail.columnDomainLanguage'),
                    allowResize: false,
                    inlineEdit: false,
                },
                {
                    property: 'snippetSetId',
                    dataIndex: 'snippetSetId',
                    label: this.$t('sw-sales-channel.detail.columnDomainSnippetSet'),
                    allowResize: false,
                    inlineEdit: false,
                },
                {
                    property: 'currencyId',
                    dataIndex: 'currencyId',
                    label: this.$t('sw-sales-channel.detail.columnDomainCurrency'),
                    allowResize: false,
                    inlineEdit: false,
                },
            ];
        },
    },
};
