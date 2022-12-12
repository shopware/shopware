/**
 * @package system-settings
 */
import template from './sw-import-export-activity-result-modal.html.twig';
import './sw-import-export-activity-result-modal.scss';

const { format } = Shopware.Utils;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['importExport'],

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
        mainEntity() {
            return this.logEntity.profile.sourceEntity;
        },

        mainEntityResult() {
            return this.logEntity.result[this.mainEntity];
        },

        result() {
            return Object.keys(this.logEntity.result).reduce((items, entityName) => {
                if (entityName !== this.mainEntity) {
                    items.push({
                        entityName,
                        ...this.logEntity.result[entityName],
                    });
                }

                return items;
            }, []);
        },

        logTypeText() {
            return this.$tc(`sw-import-export.activity.detail.${this.logEntity.activity}Label`);
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
