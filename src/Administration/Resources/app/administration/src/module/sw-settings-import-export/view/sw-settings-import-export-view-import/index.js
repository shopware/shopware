import template from './sw-settings-import-export-view-import.html.twig';
import './sw-settings-import-export-view-import.scss';

/**
 * @private
 */
Shopware.Component.register('sw-settings-import-export-view-import', {
    template,

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    methods: {
        onImportFinish() {
            this.$refs.activityGrid.fetchActivities();
        }
    }
});
