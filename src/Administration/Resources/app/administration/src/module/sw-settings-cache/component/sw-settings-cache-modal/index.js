/**
 * @package system-settings
 */
import template from './sw-settings-cache-modal.twig';

const { Mixin } = Shopware;

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
        Mixin.getByName('notification'),
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

    methods: {
        createdComponent() {
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Alt' || (event.key === 'c' && event.altKey)) {
                    event.preventDefault();
                }
            });
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
                message: this.$tc('sw-settings-cache.notifications.clearCache.started'),
            });

            this.cacheApiService.clear().then(() => {
                this.createNotificationSuccess({
                    message: this.$tc('sw-settings-cache.notifications.clearCache.success'),
                });
            }).catch(() => {
                this.createNotificationError({
                    message: this.$tc('sw-settings-cache.notifications.clearCache.error'),
                });
            });

            this.open = false;
        },
    },
};
