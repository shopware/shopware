import template from './sw-mail-template-list.html.twig';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-mail-template-list', {
    template,

    inject: [
        'repositoryFactory',
        'context'
    ],

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('notification')
    ],

    data() {
        return {
            mailTemplates: null,
            showDeleteModal: null,
            isLoading: false
        };
    },

    computed: {
        mailTemplateRepository() {
            return this.repositoryFactory.create('mail_template');
        }
    },

    methods: {
        getList() {
            this.isLoading = true;
            this.mailTemplates = null;

            const criteria = new Criteria(this.page, this.limit);

            criteria.getAssociation('salesChannels')
                .setLimit(10)
                .addAssociation('salesChannel');

            criteria.addAssociation('mailTemplateType');

            this.mailTemplateRepository.search(criteria, this.context).then((items) => {
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
                label: this.$tc('sw-mail-template.list.columnMailType'),
                allowResize: true,
                primary: true
            }, {
                property: 'description',
                dataIndex: 'description',
                label: this.$tc('sw-mail-template.list.columnDescription'),
                allowResize: true
            }, {
                property: 'salesChannels.salesChannel.name',
                dataIndex: 'salesChannels.salesChannel.name',
                label: this.$tc('sw-mail-template.list.columnSalesChannels'),
                allowResize: true,
                sortable: false
            }];
        },

        getSalesChannelsString(item) {
            if (typeof item.salesChannels === 'undefined') {
                return '';
            }

            let salesChannels = '';
            item.salesChannels.forEach((mailTemplateSalesChannel) => {
                if (salesChannels !== '') {
                    salesChannels += ', ';
                }
                salesChannels += `${mailTemplateSalesChannel.salesChannel.translated.name}`;
            });

            if (item.salesChannels.length >= 5) {
                salesChannels += '...';
            }

            return salesChannels;
        },

        onChangeLanguage(languageId) {
            this.getList(languageId);
        },

        onDuplicate(id) {
            this.mailTemplateRepository.clone(id).then((mailTemplate) => {
                this.$router.push(
                    {
                        name: 'sw.mail.template.detail',
                        params: { id: mailTemplate.id }
                    }
                );
            });
        }
    }
});
