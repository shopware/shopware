import { Component, Mixin, State } from 'src/core/shopware';
import { warn } from 'src/core/service/utils/debug.utils';
import template from './sw-product-stream-detail.html.twig';

Component.register('sw-product-stream-detail', {
    template,

    mixins: [
        Mixin.getByName('placeholder'),
        Mixin.getByName('notification'),
        Mixin.getByName('discard-detail-page-changes')('product-stream')
    ],

    data() {
        return {
            productStream: {}
        };
    },

    computed: {
        productStreamStore() {
            return State.getStore('product_stream');
        },
        productStreamFilterStore() {
            return State.getStore('product_stream_filter');
        },
        filterAssociationStore() {
            return this.productStream.getAssociation('filters');
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
                this.productStreamId = this.$route.params.id;
                if (this.productStream.isLocal) {
                    return;
                }

                this.loadEntityData();
            }
        },

        loadEntityData() {
            this.productStream = this.productStreamStore.getByIdAsync(this.productStreamId).then((productStream) => {
                this.productStream = productStream;

                this.filterAssociationStore.getList({
                    page: 1,
                    limit: 500,
                    sortBy: 'position',
                    sortDirection: 'ASC'
                });
            });
        },

        abortOnLanguageChange() {
            return this.productStream.hasChanges();
        },

        saveOnLanguageChange() {
            return this.onSave();
        },

        onChangeLanguage() {
            this.loadEntityData();
        },

        onSave() {
            const productStreamName = this.productStream.name;
            const titleSaveSuccess = this.$tc('sw-product-stream.detail.titleSaveSuccess');
            const messageSaveSuccess = this.$tc(
                'sw-product-stream.detail.messageSaveSuccess', 0, { name: productStreamName }
            );
            const titleSaveError = this.$tc('global.notification.notificationSaveErrorTitle');
            const messageSaveError = this.$tc(
                'global.notification.notificationSaveErrorMessage', 0, { entityName: productStreamName }
            );

            return this.productStream.save().then(() => {
                this.createNotificationSuccess({
                    title: titleSaveSuccess,
                    message: messageSaveSuccess
                });
            }).catch((exception) => {
                this.createNotificationError({
                    title: titleSaveError,
                    message: messageSaveError
                });
                warn(this._name, exception.message, exception.response);
            });
        }
    }
});
