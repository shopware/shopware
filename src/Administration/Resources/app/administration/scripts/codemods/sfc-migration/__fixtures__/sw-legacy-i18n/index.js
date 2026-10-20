import template from './sw-legacy-i18n.html.twig';

/**
 * @sw-package framework
 */
export default {
    template,

    props: {
        itemCount: {
            type: Number,
            required: true,
        },

        collapsed: {
            type: Boolean,
            required: true,
        },
    },

    computed: {
        // Both shapes mean the same to legacy `$t`/`$tc` and to Composition `t()`.
        title() {
            return this.$t('sw-legacy-i18n.title', { name: 'demo' });
        },

        itemLabel() {
            return this.$tc('sw-legacy-i18n.items', this.itemCount);
        },

        // A plural count does not have to be a literal to be portable.
        toggleLabel() {
            return this.$t('sw-legacy-i18n.toggle', this.collapsed ? 0 : 1);
        },

        // Both shapes are read differently by Composition `t()`.
        fallbackTitle() {
            return this.$t('sw-legacy-i18n.title', Shopware.Context.app.fallbackLocale);
        },

        namedItemLabel() {
            return this.$tc('sw-legacy-i18n.items', this.itemCount, { name: 'demo' });
        },
    },
};
