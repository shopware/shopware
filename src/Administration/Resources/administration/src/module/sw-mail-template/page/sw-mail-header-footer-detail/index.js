import { Component, Mixin, State } from 'src/core/shopware';
import { warn } from 'src/core/service/utils/debug.utils';
import template from './sw-mail-header-footer-detail.html.twig';

Component.register('sw-mail-header-footer-detail', {
    template,

    mixins: [
        Mixin.getByName('placeholder'),
        Mixin.getByName('notification')
    ],

    data() {
        return {
            mailHeaderFooter: false,
            mailHeaderFooterId: null
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
            return State.getStore('mail_header_footer');
        },

        salesChannelStore() {
            return State.getStore('sales_channel');
        },

        salesChannelAssociationStore() {
            return this.mailHeaderFooter.getAssociation('salesChannels');
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

        onSave() {
            const mailHeaderFooterName = this.mailHeaderFooter.name;

            const notificationSuccess = {
                title: this.$tc('sw-mail-header-footer.detail.titleSaveSuccess'),
                message: this.$tc(
                    'sw-mail-header-footer.detail.messageSaveSuccess', 0, { name: mailHeaderFooterName }
                )
            };

            const notificationError = {
                title: this.$tc('global.notification.notificationSaveErrorTitle'),
                message: this.$tc(
                    'global.notification.notificationSaveErrorMessage', 0, { entityName: mailHeaderFooterName }
                )
            };

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
                this.createNotificationSuccess(notificationSuccess);
            }).catch((exception) => {
                this.createNotificationError(notificationError);
                warn(this._name, exception.message, exception.response);
            });
        }
    }
});
