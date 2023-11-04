/**
 * @package system-settings
 */
import template from './sw-import-export-edit-profile-modal.html.twig';
import './sw-import-export-edit-profile-modal.scss';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

/**
 * @private
 */
export default {
    template,

    inject: [
        'repositoryFactory',
        'feature',
        'importExportProfileMapping',
        'importExportUpdateByMapping',
    ],

    mixins: [Mixin.getByName('notification')],

    props: {
        profile: {
            type: Object,
            required: false,
            default() {
                return {};
            },
        },
        show: {
            type: Boolean,
            required: false,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
            default() {
                return true;
            },
        },
    },

    data() {
        return {
            missingRequiredFields: [],
            systemRequiredFields: {},
        };
    },

    computed: {
        ...mapPropertyErrors(
            'profile',
            [
                'name',
                'sourceEntity',
                'delimiter',
                'enclosure',
                'type',
            ],
        ),

        isNew() {
            if (!this.profile || !this.profile.isNew) {
                return false;
            }

            return this.profile.isNew();
        },

        modalTitle() {
            return this.isNew ?
                this.$tc('sw-import-export.profile.newProfileLabel') :
                this.$tc('sw-import-export.profile.editProfileLabel');
        },

        saveLabelSnippet() {
            return this.isNew ?
                this.$tc('sw-import-export.profile.addProfileLabel') :
                this.$tc('sw-import-export.profile.saveProfileLabel');
        },

        showValidationError() {
            return this.missingRequiredFields.length > 0;
        },

        profileRepository() {
            return this.repositoryFactory.create('import_export_profile');
        },
    },

    watch: {
        'profile.sourceEntity': {
            handler(value) {
                if (value) {
                    this.loadSystemRequiredFieldsForEntity(value);
                }
            },
        },
        'profile.mapping': {
            handler() {
                this.importExportUpdateByMapping.removeUnusedMappings(this.profile);
            },
        },
    },

    methods: {
        saveProfile() {
            this.getParentProfileSelected().then((parentProfile) => {
                this.checkValidation(parentProfile);

                if (this.missingRequiredFields.length === 0) {
                    this.$emit('profile-save');
                }
            });
        },

        updateMapping(newProfile) {
            this.profile.mapping = newProfile;
        },

        getParentProfileSelected() {
            const criteria = new Criteria(1, 25);
            criteria.addFilter(Criteria.equals('sourceEntity', this.profile.sourceEntity));
            criteria.addFilter(Criteria.equals('systemDefault', true));

            return this.profileRepository.search(criteria).then((results) => {
                if (results.total > 0) {
                    return results[0];
                }

                return null;
            }).catch(() => {
                this.createNotificationError({
                    message: this.$tc('sw-import-export.profile.messageSearchParentProfileError'),
                });
            });
        },

        checkValidation(parentProfile) {
            // Skip validation for only export profiles
            if (this.profile.type === 'export') {
                return;
            }

            const parentMapping = parentProfile ? parentProfile.mapping : [];
            const isOnlyUpdateProfile =
                this.profile.config.createEntities === false &&
                this.profile.config.updateEntities === true;
            const validationErrors = this.importExportProfileMapping.validate(
                this.profile.sourceEntity,
                this.profile.mapping,
                parentMapping,
                isOnlyUpdateProfile,
            );

            if (validationErrors.missingRequiredFields.length > 0) {
                this.missingRequiredFields = validationErrors.missingRequiredFields;
            }
        },

        resetViolations() {
            this.missingRequiredFields = [];
        },

        loadSystemRequiredFieldsForEntity(entityName) {
            this.systemRequiredFields = this.importExportProfileMapping.getSystemRequiredFields(entityName);
        },
    },
};
