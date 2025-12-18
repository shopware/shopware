import template from './sw-mail-template-list.html.twig';
import './sw-mail-template-list.scss';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

/**
 * @sw-package after-sales
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'acl',
    ],

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('notification'),
    ],

    props: {
        /**
         * @deprecated tag:v6.8.0 - Will be removed.
         */
        searchTerm: {
            type: String,
            required: false,
            default: '',
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed together with searchTerm prop.
         */
        showSearch: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            mailTemplates: null,
            showDeleteModal: null,
            isLoading: false,
        };
    },

    computed: {
        mailTemplateRepository() {
            return this.repositoryFactory.create('mail_template');
        },

        skeletonItemAmount() {
            return this.mailTemplates && this.mailTemplates.length !== 0 ? this.mailTemplates.length : 3;
        },

        showListing() {
            return !!this.mailTemplates && this.mailTemplates.length !== 0;
        },

        assetFilter() {
            return Shopware.Filter.getByName('asset');
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed together with searchTerm prop.
         */
        currentSearchTerm() {
            if (this.searchTerm) {
                return this.searchTerm;
            }

            return this.term;
        },
    },

    watch: {
        /**
         * @deprecated tag:v6.8.0 - Will be removed together with searchTerm prop.
         */
        searchTerm() {
            this.getList();
        },
    },

    methods: {
        /**
         * @deprecated tag:v6.8.0 - `currentSearchTerm` will be replaced with `this.term`.
         */
        getList() {
            this.isLoading = true;

            const criteria = new Criteria(this.page, this.limit);
            criteria.addAssociation('mailTemplateType').addSorting(Criteria.sort('mailTemplateType.name'));

            if (this.currentSearchTerm) {
                criteria.setTerm(this.currentSearchTerm);
            }

            this.mailTemplateRepository.search(criteria).then((items) => {
                this.total = items.total;
                this.mailTemplates = items;
                this.isLoading = false;

                return this.mailTemplates;
            });
        },

        getListColumns() {
            return [
                {
                    property: 'mailTemplateType.name',
                    dataIndex: 'mailTemplateType.name',
                    label: 'sw-mail-template.list.columnMailType',
                    allowResize: true,
                    routerLink: 'sw.mail.template.detail',
                    primary: true,
                },
                {
                    property: 'description',
                    dataIndex: 'description',
                    label: 'sw-mail-template.list.columnDescription',
                    allowResize: true,
                },
            ];
        },

        onChangeLanguage(languageId) {
            this.getList(languageId);
        },

        onDuplicate(id) {
            this.isLoading = true;
            this.mailTemplateRepository.clone(id).then((mailTemplate) => {
                this.getList();
                this.isLoading = false;
                this.$router.push({
                    name: 'sw.mail.template.detail',
                    params: { id: mailTemplate.id },
                });
            });
        },

        updateRecords(result) {
            this.mailTemplates = result;
        },
    },
};
