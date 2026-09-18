/**
 * @sw-package fundamentals@framework
 */
import notificationMixin from 'shopware:mixins/notification';
import template from './sw-settings-basic-information.html.twig';
import './sw-settings-basic-information.scss';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    mixins: [
        notificationMixin,
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
