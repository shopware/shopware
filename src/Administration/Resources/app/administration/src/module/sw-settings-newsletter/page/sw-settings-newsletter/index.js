import template from './sw-settings-newsletter.html.twig';

/**
 * @package buyers-experience
 */

const { Mixin } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

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

    methods: {
        saveFinish() {
            this.isSaveSuccessful = false;
        },

        onSave() {
            this.isSaveSuccessful = false;
            this.isLoading = true;

            Promise.all([
                this.$refs.systemConfig.saveAll(),
            ]).then(() => {
                this.isLoading = false;
                this.isSaveSuccessful = true;
            }).catch((err) => {
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
