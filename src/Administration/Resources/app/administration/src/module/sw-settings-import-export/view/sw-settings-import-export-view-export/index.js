import template from './sw-settings-import-export-view-export.html.twig';
import './sw-settings-import-export-view-export.scss';

Shopware.Component.register('sw-settings-import-export-view-export', {
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
