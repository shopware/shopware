import template from './sw-shopware-updates-extensions.html.twig';
import { featureService, wrapComponentConfig } from '../dependencies';

/**
 * @sw-package framework
 * @private
 */
export default wrapComponentConfig({
    template,

    inject: [featureService],

    props: {
        isLoading: {
            type: Boolean,
        },
        extensions: {
            type: Array,
            default: () => [],
        },
    },
    computed: {
        hasExtensions(): boolean {
            return this.extensions.length > 0;
        },

        columns() {
            return [
                {
                    property: 'name',
                    label: this.$t('sw-settings-shopware-updates.extensions.columns.name'),
                    rawData: true,
                },
                {
                    property: 'icon',
                    label: this.$t('sw-settings-shopware-updates.extensions.columns.available'),
                    rawData: true,
                },
            ];
        },
    },

    methods: {
        openMyExtensions() {
            void this.$router.push({
                name: 'sw.extension.my-extensions.listing.app',
            });
        },
    },
});
