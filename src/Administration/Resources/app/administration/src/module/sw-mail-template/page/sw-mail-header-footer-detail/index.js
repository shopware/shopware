import template from './sw-mail-header-footer-detail.html.twig';

const { Component, Mixin, StateDeprecated } = Shopware;
const { warn } = Shopware.Utils.debug;

Component.register('sw-mail-header-footer-detail', {
    template,

    mixins: [
        Mixin.getByName('placeholder'),
        Mixin.getByName('notification')
    ],

    inject: ['entityMappingService'],

    data() {
        return {
            mailHeaderFooter: false,
            mailHeaderFooterId: null,
            isLoading: false,
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
        identifier() {
            return this.placeholder(this.mailHeaderFooter, 'name');
        },

        mailHeaderFooterStore() {
            return StateDeprecated.getStore('mail_header_footer');
        },

        salesChannelStore() {
            return StateDeprecated.getStore('sales_channel');
        },

        salesChannelAssociationStore() {
            return this.mailHeaderFooter.getAssociation('salesChannels');
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
        createdComponent() {
            if (this.$route.params.id) {
                this.mailHeaderFooterId = this.$route.params.id;
                this.loadEntityData();
            }
        },

        loadEntityData() {
            this.mailHeaderFooter = this.mailHeaderFooterStore.getById(this.mailHeaderFooterId);
        },

        abortOnLanguageChange() {
            return this.mailHeaderFooter.hasChanges();
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
            const mailHeaderFooterName = this.mailHeaderFooter.name || this.placeholder(this.mailHeaderFooter, 'name');

            const notificationError = {
                title: this.$tc('global.default.error'),
                message: this.$tc(
                    'global.notification.notificationSaveErrorMessage', 0, { entityName: mailHeaderFooterName }
                )
            };
            this.isSaveSuccessful = false;
            this.isLoading = true;

            if (!this.mailHeaderFooter.salesChannels) {
                this.mailHeaderFooter.salesChannels = [];
            }

            return this.mailHeaderFooter.save(false).then(() => {
                this.salesChannelStore.forEach((salesChannel) => {
                    if (this.mailHeaderFooter.salesChannels.findIndex(entry => entry === salesChannel.id) >= 0) {
                        salesChannel.mailHeaderFooterId = this.mailHeaderFooter.id;
                    } else if (salesChannel.mailHeaderFooterId === this.mailHeaderFooter.id) {
                        salesChannel.mailHeaderFooterId = null;
                    }
                    return salesChannel.save(false);
                });
            }).then(() => {
                this.isLoading = false;
                this.isSaveSuccessful = true;
            }).catch((exception) => {
                this.isLoading = false;
                this.createNotificationError(notificationError);
                warn(this._name, exception.message, exception.response);
            });
        }
    }
});
