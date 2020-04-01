import template from './sw-import-export-view-export.html.twig';
import './sw-import-export-view-export.scss';

/**
 * @private
 */
Shopware.Component.register('sw-import-export-view-export', {
    template,

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    methods: {
        onExportFinish() {
            this.$refs.activityGrid.fetchActivities();
        }
    }
});
