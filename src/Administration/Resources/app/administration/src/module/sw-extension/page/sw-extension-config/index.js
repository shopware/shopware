import template from './sw-extension-config.html.twig';
import './sw-extension-config.scss';

const { Component, Mixin } = Shopware;

/**
 * @private
 */
Component.register('sw-extension-config', {
    template,

    inject: [
        'shopwareExtensionService',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        namespace: {
            type: String,
            required: true,
        },
    },

    data() {
        return {
            salesChannelId: null,
            extension: null,
        };
    },

    computed: {
        domain() {
            return `${this.namespace}.config`;
        },

        myExtensions() {
            return Shopware.State.get('shopwareExtensions').myExtensions;
        },

        defaultThemeAsset() {
            return Shopware.Filter.getByName('asset')('administration/static/img/theme/default_theme_preview.jpg');
        },

        image() {
            if (this.extension?.icon) {
                return this.extension.icon;
            }

            if (this.extension?.iconRaw) {
                return `data:image/png;base64, ${this.extension.iconRaw}`;
            }

            return this.defaultThemeAsset;
        },

        extensionLabel() {
            return this.extension?.label ?? this.namespace;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            if (!this.myExtensions.data.length) {
                await this.shopwareExtensionService.updateExtensionData();
            }

            this.extension = this.myExtensions.data.find((ext) => {
                return ext.name === this.namespace;
            });
        },

        onSave() {
            this.$refs.systemConfig.saveAll().then(() => {
                this.createNotificationSuccess({
                    message: this.$tc('sw-extension-store.component.sw-extension-config.messageSaveSuccess'),
                });
            }).catch((err) => {
                this.createNotificationError({
                    message: err,
                });
            });
        },
    },
});
