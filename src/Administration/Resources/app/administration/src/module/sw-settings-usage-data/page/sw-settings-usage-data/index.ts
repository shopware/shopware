import template from './sw-settings-usage-data.html.twig';

/**
 * @private
 *
 * @sw-package framework
 */
export default Shopware.Component.wrapComponentConfig({
    name: 'sw-settings-usage-data',

    template,

    computed: {
        tabs() {
            return [
                {
                    label: this.$t('sw-settings-usage-data-general.tabHeadline'),
                    name: 'sw.settings.usage.data.index.general',
                    onClick: () => {
                        void this.$router.push({ name: 'sw.settings.usage.data.index.general' });
                    },
                },
            ];
        },
    },
});
