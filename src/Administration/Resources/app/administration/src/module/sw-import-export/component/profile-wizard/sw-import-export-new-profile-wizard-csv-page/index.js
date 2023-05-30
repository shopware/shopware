/**
 * @package system-settings
 */
import template from './sw-import-export-new-profile-wizard-csv-page.html.twig';
import './sw-import-export-new-profile-wizard-csv-page.scss';

const { Mixin } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'importExport',
    ],

    mixins: [Mixin.getByName('notification')],

    props: {
        profile: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            csvFile: null,
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.$emit('next-disable');
        },

        onFileChange() {
            if (this.csvFile === null) {
                this.profile.mapping = [];
                this.$emit('next-disable');
                return Promise.resolve();
            }

            return this.importExport.getMappingFromTemplate(
                this.csvFile,
                this.profile.sourceEntity,
                this.profile.delimiter,
                this.profile.enclosure,
            ).then((mapping) => {
                const transformedMapping = this.transformMapping(mapping);

                this.$set(this.profile, 'mapping', transformedMapping);
                this.$emit('next-allow');

                if (transformedMapping.length === 1) {
                    this.createNotificationWarning({
                        message: this.$tc('sw-import-export.profile.messageCsvTemplateUploadWarning'),
                        duration: 10000,
                    });
                }
            }).catch((error) => {
                this.profile.mapping = [];
                this.$emit('next-disable');
                let message = this.$tc('sw-import-export.profile.messageCsvTemplateUploadError');

                const errorCode = error.response?.data?.errors?.[0]?.code;
                if (errorCode === 'CONTENT__IMPORT_EXPORT_FILE_EMPTY') {
                    message = this.$tc('sw-import-export.profile.messageCsvTemplateUploadEmptyError');
                }

                this.createNotificationError({
                    message,
                });
            });
        },

        /**
         * Iterates over every mapping and only returns the key and mappedKey of that mapping.
         * We do this to not populate the database with unnecessary data.
         * @param mapping
         * @returns {Array}
         */
        transformMapping(mapping) {
            return mapping.map(currentMapping => {
                return {
                    key: currentMapping.key,
                    mappedKey: currentMapping.mappedKey,
                };
            });
        },
    },
};
