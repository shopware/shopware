import template from './sw-import-export-activity-detail-modal.html.twig';
import './sw-import-export-activity-detail-modal.scss';

const { Component, Mixin } = Shopware;
const { format } = Shopware.Utils;

/**
 * @deprecated tag:v6.5.0 - Remove component + snippets. This component is replaced by the
 *  `sw-import-export-activity-log-info-modal` and `sw-import-export-activity-result-modal`.
 * @private
 */
Component.register('sw-import-export-activity-detail-modal', {
    template,

    inject: ['importExport'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        logEntity: {
            type: Object,
            required: false,
            default() {
                return {};
            },
        },
    },

    computed: {
        typeText() {
            return this.$tc(`sw-import-export.activity.detail.${this.logEntity.activity}Label`);
        },
    },

    methods: {
        calculateFileSize(size) {
            return format.fileSize(size);
        },

        /**
         * @deprecated tag:v6.5.0 - Remove unused method, use openDownload instead
         */
        getDownloadUrl() {
            Shopware.Utils.debug.error('The method getDownloadUrl has been replaced with openDownload.');

            return '';
        },

        async openDownload(id) {
            return window.open(await this.importExport.getDownloadUrl(id), '_blank');
        },

        getStateLabel(state) {
            const translationKey = `sw-import-export.activity.status.${state}`;

            return this.$te(translationKey) ? this.$tc(translationKey) : state;
        },
    },
});
