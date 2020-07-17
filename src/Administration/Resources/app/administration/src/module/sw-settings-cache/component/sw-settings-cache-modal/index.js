import template from './sw-settings-cache-modal.twig';

const { Component, Mixin } = Shopware;

Component.register('sw-settings-cache-modal', {
    template,

    shortcuts: {
        'SYSTEMKEY+c': 'openModal'
    },

    mixins: [
        Mixin.getByName('notification')
    ],

    inject: [
        'cacheApiService',
        'acl'
    ],

    created() {
        this.createdComponent();
    },

    data() {
        return {
            open: false
        };
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
                title: this.$tc('global.default.info'),
                message: this.$tc('sw-settings-cache.notifications.clearCache.started')
            });

            this.cacheApiService.clear().then(() => {
                this.createNotificationSuccess({
                    title: this.$tc('global.default.success'),
                    message: this.$tc('sw-settings-cache.notifications.clearCache.success')
                });
            }).catch(() => {
                this.createNotificationError({
                    title: this.$tc('global.default.error'),
                    message: this.$tc('sw-settings-cache.notifications.clearCache.error')
                });
            });

            this.open = false;
        }
    },

    watch: {
        open() {
            if (!this.open) {
                return;
            }

            this.$nextTick(() => {
                this.$refs.button.$el.focus();
            });
        }
    }
});
