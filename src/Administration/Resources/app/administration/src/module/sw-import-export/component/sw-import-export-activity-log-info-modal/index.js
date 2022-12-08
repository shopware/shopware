/**
 * @package system-settings
 */
import template from './sw-import-export-activity-log-info-modal.html.twig';
import './sw-import-export-activity-log-info-modal.scss';

const { Mixin } = Shopware;
const { format } = Shopware.Utils;

/**
 * @private
 */
export default {
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
            return this.$tc(`sw-import-export.activity.logInfo.${this.logEntity.activity}Label`);
        },

        stateClass() {
            return {
                'sw-import-export-activity-log-info-modal__item-state--processing': this.logEntity.state === 'progress',
            };
        },
    },

    methods: {
        calculateFileSize(size) {
            return format.fileSize(size);
        },

        async openDownload(id) {
            return window.open(await this.importExport.getDownloadUrl(id), '_blank');
        },

        getStateLabel(state) {
            const translationKey = `sw-import-export.activity.status.${state}`;

            return this.$te(translationKey) ? this.$tc(translationKey) : state;
        },
    },
};
