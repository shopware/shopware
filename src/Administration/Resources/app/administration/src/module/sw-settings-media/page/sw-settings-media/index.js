/**
 * @sw-package innovation
 */

import template from './sw-settings-media.html.twig';

const { Mixin } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'systemConfigApiService',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            isLoading: false,
            isSaveSuccessful: false,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            this.isLoading = true;
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        onSave() {
            this.isSaveSuccessful = false;
            this.isLoading = true;

            this.$refs.systemConfig
                .saveAll()
                .then(() => {
                    this.isLoading = false;
                    this.isSaveSuccessful = true;
                })
                .catch((err) => {
                    this.isLoading = false;
                    this.createNotificationError({
                        message: err,
                    });
                });
        },

        onLoadingChanged(loading) {
            this.isLoading = loading;
        },
    },
};
