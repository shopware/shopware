/**
 * @sw-package framework
 */
import notificationMixin from 'shopware:mixins/notification';
import template from './sw-settings-cache-modal.twig';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    shortcuts: {
        'SYSTEMKEY+c': 'openModal',
    },

    inject: [
        'cacheApiService',
        'acl',
    ],

    mixins: [
        notificationMixin,
    ],

    data() {
        return {
            open: false,
        };
    },

    watch: {
        open() {
            if (!this.open) {
                return;
            }

            this.$nextTick(() => {
                this.$refs.button.$el.focus();
            });
        },
    },

    created() {
        this.createdComponent();
    },

    beforeUnmount() {
        this.beforeUnmountComponent();
    },

    methods: {
        createdComponent() {
            document.addEventListener('keydown', this.keydownEventListener);
        },

        beforeUnmountComponent() {
            document.removeEventListener('keydown', this.keydownEventListener);
        },

        keydownEventListener(event) {
            if (event.key === 'Alt' || (event.key === 'c' && event.altKey)) {
                event.preventDefault();
            }
        },

        openModal() {
            if (!this.acl.can('system.clear_cache')) {
                return;
            }

            this.open = true;
        },

        closeModal() {
            this.open = false;
        },

        clearCache() {
            this.createNotificationInfo({
                message: this.$t('sw-settings-cache.notifications.clearCache.started'),
            });

            this.cacheApiService
                .clear()
                .then(() => {
                    this.createNotificationSuccess({
                        message: this.$t('sw-settings-cache.notifications.clearCache.success'),
                    });
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$t('sw-settings-cache.notifications.clearCache.error'),
                    });
                });

            this.open = false;
        },
    },
};
