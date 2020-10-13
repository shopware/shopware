import template from './sw-mail-header-footer-detail.html.twig';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { warn } = Shopware.Utils.debug;
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

Component.register('sw-mail-header-footer-detail', {
    template,

    mixins: [
        Mixin.getByName('placeholder'),
        Mixin.getByName('notification')
    ],

    inject: ['entityMappingService', 'repositoryFactory', 'acl'],

    shortcuts: {
        'SYSTEMKEY+S': {
            active() {
                return this.allowSave;
            },
            method: 'onSave'
        }
    },

    data() {
        return {
            mailHeaderFooter: null,
            mailHeaderFooterId: null,
            isLoading: true,
            isSaveSuccessful: false,
            editorConfig: {
                enableBasicAutocompletion: true
            }
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier)
        };
    },

    computed: {
        ...mapPropertyErrors('mailHeaderFooter', [
            'name'
        ]),

        identifier() {
            return this.placeholder(this.mailHeaderFooter, 'name');
        },

        mailHeaderFooterRepository() {
            return this.repositoryFactory.create('mail_header_footer');
        },

        mailHeaderFooterCriteria() {
            const criteria = new Criteria();

            criteria.addAssociation('salesChannels');

            return criteria;
        },

        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },

        completerFunction() {
            return (function completerWrapper(entityMappingService) {
                function completerFunction(prefix) {
                    const properties = [];
                    Object.keys(
                        entityMappingService.getEntityMapping(
                            prefix, { salesChannel: 'sales_channel' }
                        )
                    ).forEach((val) => {
                        properties.push({
                            value: val
                        });
                    });
                    return properties;
                }
                return completerFunction;
            }(this.entityMappingService));
        },

        allowSave() {
            return this.mailHeaderFooter && this.mailHeaderFooter.isNew()
                ? this.acl.can('mail_templates.creator')
                : this.acl.can('mail_templates.editor');
        },

        tooltipSave() {
            if (!this.allowSave) {
                return {
                    message: this.$tc('sw-privileges.tooltip.warning'),
                    disabled: this.allowSave,
                    showOnDisabledElements: true
                };
            }

            const systemKey = this.$device.getSystemKey();

            return {
                message: `${systemKey} + S`,
                appearance: 'light'
            };
        }
    },

    created() {
        this.createdComponent();
    },

    watch: {
        '$route.params.id'() {
            this.createdComponent();
        }
    },

    methods: {
        async createdComponent() {
            if (this.$route.params.id) {
                this.mailHeaderFooterId = this.$route.params.id;
                await this.loadEntityData();
            }

            this.isLoading = false;
        },

        async loadEntityData() {
            this.isLoading = true;

            this.mailHeaderFooter = await this.mailHeaderFooterRepository.get(
                this.mailHeaderFooterId,
                Shopware.Context.api,
                this.mailHeaderFooterCriteria
            );

            this.isLoading = false;
        },

        abortOnLanguageChange() {
            return this.this.mailHeaderFooterRepository.hasChanges(this.mailHeaderFooter);
        },

        saveOnLanguageChange() {
            return this.onSave();
        },

        onChangeLanguage() {
            this.loadEntityData();
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        onSave() {
            this.isSaveSuccessful = false;
            this.isLoading = true;

            return this.mailHeaderFooterRepository.save(this.mailHeaderFooter, Shopware.Context.api)
                .then(() => {
                    return this.loadEntityData();
                })
                .then(() => {
                    this.isSaveSuccessful = true;
                })
                .catch((error) => {
                    const notificationError = {
                        message: this.$tc(
                            'global.notification.notificationSaveErrorMessageRequiredFieldsInvalid'
                        )
                    };

                    this.createNotificationError(notificationError);
                    warn(error);
                })
                .finally(() => {
                    this.isLoading = false;
                });
        }
    }
});
