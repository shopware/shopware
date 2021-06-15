import template from './sw-event-action-detail.html.twig';
import './sw-event-action-detail.scss';

const snakeCase = Shopware.Utils.string.snakeCase;
const { Component, Utils, Mixin, Data: { Criteria }, Classes: { ShopwareError } } = Shopware;
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

Component.register('sw-event-action-detail', {
    template,

    inject: [
        'repositoryFactory',
        'businessEventService',
        'acl',
        'customFieldDataProviderService',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    shortcuts: {
        'SYSTEMKEY+S': 'onSave',
        ESCAPE: 'onCancel',
    },

    props: {
        eventActionId: {
            type: String,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            businessEvents: null,
            eventAction: null,
            isLoading: false,
            recipients: [],
            isSaveSuccessful: false,
            customFieldSets: null,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
    },

    computed: {
        ...mapPropertyErrors('eventAction', [
            'eventName',
        ]),

        eventActionMailTemplateError() {
            if (this.eventAction.config.mail_template_id) {
                return null;
            }

            return new ShopwareError({
                code: 'EVENT_ACTION_DETAIL_MISSING_MAIL_TEMPLATE_ID',
                detail: this.$tc('global.error-codes.c1051bb4-d103-4f74-8988-acbcafc7fdc3'),
            });
        },

        eventActionRepository() {
            return this.repositoryFactory.create('event_action');
        },

        eventActionCriteria() {
            const criteria = new Criteria();
            criteria.addAssociation('salesChannels');
            criteria.addAssociation('rules');

            return criteria;
        },

        mailTemplateCriteria() {
            const criteria = new Criteria();
            criteria.addAssociation('mailTemplateType');

            return criteria;
        },

        identifier() {
            if (this.eventAction && this.eventAction.eventName) {
                return this.$tc(`global.businessEvents.${snakeCase(this.eventAction.eventName)}`);
            }

            return this.$tc('sw-event-action.detail.titleNewEntity');
        },

        tooltipCancel() {
            return {
                message: 'ESC',
                appearance: 'light',
            };
        },

        tooltipSave() {
            if (this.acl.can('event_action.editor')) {
                const systemKey = this.$device.getSystemKey();

                return {
                    message: `${systemKey} + S`,
                    appearance: 'light',
                };
            }

            return {
                showDelay: 300,
                message: this.$tc('sw-privileges.tooltip.warning'),
                disabled: this.acl.can('event_action.editor'),
                showOnDisabledElements: true,
            };
        },

        showCustomFields() {
            return this.eventAction && this.customFieldSets && this.customFieldSets.length > 0;
        },
    },

    watch: {
        eventActionId() {
            this.loadData();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.loadData();
        },

        loadData() {
            this.isLoading = true;

            return Promise
                .all([this.getBusinessEvents(), this.getEventAction(), this.loadCustomFieldSets()])
                .then(([businessEvents, eventAction, customFieldSets]) => {
                    this.businessEvents = this.filterMailAwareEvents(this.addTranslatedEventNames(businessEvents));
                    this.eventAction = eventAction;
                    this.customFieldSets = customFieldSets;

                    this.isLoading = false;

                    this.buildRecipients();

                    return Promise.resolve([businessEvents, eventAction, customFieldSets]);
                })
                .catch((exception) => {
                    this.createNotificationError({
                        message: exception,
                    });
                    this.isLoading = false;

                    return Promise.reject(exception);
                });
        },

        loadCustomFieldSets() {
            return this.customFieldDataProviderService.getCustomFieldSets('event_action');
        },

        getEventAction() {
            if (!this.eventActionId) {
                const newEventAction = this.eventActionRepository.create();
                newEventAction.eventName = '';
                newEventAction.actionName = 'action.mail.send';
                newEventAction.active = false;
                newEventAction.config = {
                    mail_template_type_id: Utils.createId(),
                };

                return newEventAction;
            }

            return this.eventActionRepository.get(
                this.eventActionId,
                Shopware.Context.api,
                this.eventActionCriteria,
            );
        },

        getBusinessEvents() {
            return this.businessEventService.getBusinessEvents();
        },

        addTranslatedEventNames(businessEvents) {
            return businessEvents.map((businessEvent) => {
                const camelCaseEventName = snakeCase(businessEvent.name);
                return { ...businessEvent, label: this.$tc(`global.businessEvents.${camelCaseEventName}`) };
            });
        },

        filterMailAwareEvents(businessEvents) {
            return businessEvents.filter((businessEvent) => {
                return businessEvent.mailAware;
            });
        },

        onSave() {
            this.isLoading = true;

            if (!this.eventAction.config.mail_template_id) {
                this.isLoading = false;
                return Promise.reject(this.eventActionMailTemplateError);
            }

            this.processRecipientList();

            return this.eventActionRepository.save(this.eventAction)
                .then(() => {
                    if (typeof this.eventAction.isNew === 'function' && this.eventAction.isNew()) {
                        this.$router.push({
                            name: 'sw.event.action.detail', params: { id: this.eventAction.id },
                        });
                        return Promise.resolve(this.eventAction);
                    }
                    this.recipients = [];
                    this.loadData();
                    this.isSaveSuccessful = true;

                    return Promise.resolve(this.eventAction);
                })
                .catch((exception) => {
                    this.createNotificationError({
                        message: this.$tc('global.notification.notificationSaveErrorMessageRequiredFieldsInvalid'),
                    });
                    this.isLoading = false;

                    return Promise.reject(exception);
                });
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        processRecipientList() {
            // If no recipients are present delete recipients key from config
            if (!this.recipients.length) {
                this.$delete(this.eventAction.config, 'recipients');
                return;
            }

            // Otherwise prepare object with email as key and name as value
            const recipients = {};

            this.recipients.forEach((item) => {
                if (item.email && item.name) {
                    recipients[item.email] = item.name;
                }
            });

            this.eventAction.config.recipients = recipients;
        },

        onUpdateRecipientsList(list) {
            this.recipients = list;
        },

        snakeCaseEventName(value) {
            return snakeCase(value);
        },

        buildRecipients() {
            if (this.eventAction.config.recipients) {
                Object.entries(this.eventAction.config.recipients).forEach(([key, value]) => {
                    this.recipients.push({
                        email: key,
                        name: value,
                    });
                });
            }
        },
    },
});
