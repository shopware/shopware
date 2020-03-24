import template from './sw-settings-import-export-view-export.html.twig';
import './sw-settings-import-export-view-export.scss';

Shopware.Component.register('sw-settings-import-export-view-export', {
    template,

    inject: ['repositoryFactory'],

    data() {
        return {
            exportLoading: false,
            activityLoading: false
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    methods: {
        onExportFinish() {
            console.log('export dfinish : ');
            this.$refs.activityGrid.fetchActivities();
        }
    }
});
