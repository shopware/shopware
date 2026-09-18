/**
 * @sw-package fundamentals@framework
 */
import notificationMixin from 'shopware:mixins/notification';
import template from './sw-users-permissions.html.twig';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'feature',
        'acl',
    ],

    mixins: [
        notificationMixin,
    ],

    data() {
        return {
            isLoading: true,
            isSaveSuccessful: false,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    methods: {
        reloadUserListing() {
            if (this.$refs.userListing) {
                this.$refs.userListing.getList();
            }

            if (this.$refs.roleListing) {
                this.$refs.roleListing.getList();
            }
        },

        onChangeLoading(loading) {
            this.isLoading = loading;
        },

        async onSave() {
            this.isLoading = true;
            this.isSaveSuccessful = false;

            try {
                await this.$refs.configuration.$refs.systemConfig.saveAll();
                this.isLoading = false;
                this.isSaveSuccessful = true;
            } catch (error) {
                this.isLoading = false;
                this.createNotificationError({
                    message: error.message,
                });
            }
        },

        onSaveFinish() {
            this.isSaveSuccessful = false;
        },
    },
};
