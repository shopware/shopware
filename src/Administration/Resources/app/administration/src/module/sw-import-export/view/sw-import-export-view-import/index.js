import template from './sw-import-export-view-import.html.twig';
import './sw-import-export-view-import.scss';

/**
 * @private
 */
Shopware.Component.register('sw-import-export-view-import', {
    template,

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    methods: {
        reloadContent() {
            this.$refs.activityGrid.fetchActivities();
        },
    },
});
