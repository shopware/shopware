/**
 * @sw-package discovery
 */

import template from './sw-settings-sitemap.html.twig';
import { notificationMixin } from '../dependencies';

/**
 * @public
 */
export default {
    template,

    mixins: [notificationMixin],

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
