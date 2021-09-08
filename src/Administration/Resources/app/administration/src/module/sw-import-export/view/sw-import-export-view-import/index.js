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
        reloadContent(log) {
            this.$refs.activityGrid.addActivity(log);
            this.$refs.activityGrid.fetchActivities();
        },
    },
});
