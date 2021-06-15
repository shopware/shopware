import template from './sw-event-action-list.html.twig';
import './sw-event-action-list.scss';

const snakeCase = Shopware.Utils.string.snakeCase;
const { Component, Mixin, Data: { Criteria } } = Shopware;

Component.register('sw-event-action-list', {
    template,

    inject: [
        'repositoryFactory',
        'acl',
    ],

    mixins: [
        Mixin.getByName('listing'),
    ],

    data() {
        return {
            items: null,
            sortBy: 'eventName',
            sortDirection: 'ASC',
            isLoading: false,
            mailTemplates: null,
            total: 0,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        mailTemplateRepository() {
            return this.repositoryFactory.create('mail_template');
        },

        eventActionRepository() {
            return this.repositoryFactory.create('event_action');
        },

        mailTemplateCriteria() {
            const criteria = new Criteria();
            criteria.addAssociation('mailTemplateType');

            return criteria;
        },

        eventActionCriteria() {
            const criteria = new Criteria();

            criteria.setTerm(null);
            if (this.term) {
                // php implementation splits the term by each dot, so we do a custom search
                const terms = this.term.split(' ');
                const fields = ['eventName', 'actionName', 'rules.name'];

                fields.forEach((field) => {
                    terms.forEach((term) => {
                        if (term.length > 1) {
                            criteria.addQuery(Criteria.contains(field, term), 500);
                        }
                    });
                });
            }
            criteria.addAssociation('salesChannels');
            criteria.addAssociation('rules');
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection));
            criteria.addFilter(Criteria.equals('actionName', 'action.mail.send'));
            criteria.addFilter(Criteria.not('and', [
                Criteria.equals('config.mail_template_id', null),
            ]));

            return criteria;
        },

        eventActionColumns() {
            return [{
                property: 'eventName',
                dataIndex: 'eventName',
                label: 'sw-event-action.list.columnEventName',
                routerLink: 'sw.event.action.detail',
                multiLine: true,
                allowResize: true,
                primary: true,
            }, {
                property: 'title',
                dataIndex: 'title',
                label: 'sw-event-action.list.columnTitle',
                routerLink: 'sw.event.action.detail',
                multiLine: true,
                allowResize: true,
            }, {
                property: 'salesChannels',
                dataIndex: 'salesChannels',
                label: 'sw-event-action.list.columnSalesChannel',
                sortable: false,
                allowResize: true,
                multiLine: true,
            }, {
                property: 'rules',
                dataIndex: 'rules',
                label: 'sw-event-action.list.columnRules',
                sortable: false,
                allowResize: true,
                multiLine: true,
            }, {
                property: 'mailTemplate',
                label: 'sw-event-action.list.columnMailTemplate',
                multiLine: true,
                sortable: false,
            }, {
                property: 'active',
                dataIndex: 'active',
                label: 'sw-event-action.list.columnActive',
                align: 'center',
                allowResize: true,
            }];
        },
    },

    methods: {
        getList() {
            this.isLoading = true;

            return this.eventActionRepository.search(this.eventActionCriteria)
                .then((response) => {
                    this.items = response;
                    this.total = response.total;
                    this.isLoading = false;
                });
        },

        fetchMailTemplates(eventActions) {
            this.isLoading = true;

            const mailTemplateIds = eventActions.map((item) => {
                return item.config.mail_template_id;
            });

            this.mailTemplateCriteria.setIds(mailTemplateIds);

            return this.mailTemplateRepository.search(this.mailTemplateCriteria)
                .then((mailTemplates) => {
                    this.mailTemplates = mailTemplates;
                    this.isLoading = false;
                });
        },

        mailTemplateDescription(eventAction) {
            const id = eventAction.config.mail_template_id;

            const mailTemplate = this.mailTemplates.find((item) => {
                return item.id === id;
            });

            if (!mailTemplate) {
                return '';
            }

            return mailTemplate.translated.description;
        },

        mailTemplateTypeName(eventAction) {
            const id = eventAction.config.mail_template_id;

            const mailTemplate = this.mailTemplates.find((item) => {
                return item.id === id;
            });

            if (!mailTemplate || !mailTemplate.mailTemplateType) {
                return '';
            }

            return mailTemplate.mailTemplateType.translated.name;
        },

        snakeCaseEventName(value) {
            return snakeCase(value);
        },
    },
});
