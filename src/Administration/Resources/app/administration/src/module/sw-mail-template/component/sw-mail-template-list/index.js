import template from './sw-mail-template-list.html.twig';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'acl'],

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('notification'),
    ],

    props: {
        searchTerm: {
            type: String,
            required: false,
            default: '',
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
    },

    watch: {
        searchTerm() {
            this.getList();
        },
    },

    methods: {
        getList() {
            this.isLoading = true;

            const criteria = new Criteria(this.page, this.limit);
            criteria
                .addAssociation('mailTemplateType')
                .addSorting(Criteria.sort('mailTemplateType.name'));

            if (this.searchTerm) {
                criteria.setTerm(this.searchTerm);
            }

            this.mailTemplateRepository.search(criteria).then(items => {
                this.total = items.total;
                this.mailTemplates = items;
                this.isLoading = false;

                return this.mailTemplates;
            });
        },

        getListColumns() {
            return [{
                property: 'mailTemplateType.name',
                dataIndex: 'mailTemplateType.name',
                label: 'sw-mail-template.list.columnMailType',
                allowResize: true,
                routerLink: 'sw.mail.template.detail',
                primary: true,
            }, {
                property: 'description',
                dataIndex: 'description',
                label: 'sw-mail-template.list.columnDescription',
                allowResize: true,
            }];
        },

        onChangeLanguage(languageId) {
            this.getList(languageId);
        },

        onDuplicate(id) {
            this.isLoading = true;
            this.mailTemplateRepository.clone(id).then((mailTemplate) => {
                this.getList();
                this.isLoading = false;
                this.$router.push(
                    {
                        name: 'sw.mail.template.detail',
                        params: { id: mailTemplate.id },
                    },
                );
            });
        },

        updateRecords(result) {
            this.mailTemplates = result;
        },
    },
};
