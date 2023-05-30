/**
 * @package system-settings
 */
import template from './sw-import-export-new-profile-wizard.html.twig';
import './sw-import-export-new-profile-wizard.scss';

const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'feature',
        'importExportProfileMapping',
    ],

    props: {
        profile: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            nextButtonDisabled: true,
            missingRequiredFields: [],
            systemRequiredFields: {},
            csvUploadPagePosition: 1,
            currentlyActivePage: 0,
            pagesCount: 3,
        };
    },

    computed: {
        profileRepository() {
            return this.repositoryFactory.create('import_export_profile');
        },

        showValidationError() {
            return this.missingRequiredFields.length > 0;
        },

        showNextButton() {
            return this.currentlyActivePage >= this.pagesCount - 1;
        },

        showCsvSkipButton() {
            return this.currentlyActivePage === this.csvUploadPagePosition;
        },

        parentProfileCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addFilter(Criteria.equals('sourceEntity', this.profile.sourceEntity));
            criteria.addFilter(Criteria.equals('systemDefault', true));

            return criteria;
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
    },

    methods: {
        onClose() {
            this.$emit('close');
        },

        onFinish() {
            return this.saveProfile();
        },

        pageTitleSnippet(snippet) {
            return `${this.$tc('sw-import-export.profile.newProfileLabel')} - ${this.$tc(snippet)}`;
        },

        onNextAllow() {
            this.nextButtonDisabled = false;
        },

        onNextDisable() {
            this.nextButtonDisabled = true;
        },

        loadSystemRequiredFieldsForEntity(entityName) {
            this.systemRequiredFields = this.importExportProfileMapping.getSystemRequiredFields(entityName);
        },

        saveProfile() {
            return this.getParentProfileSelected().then((parentProfile) => {
                this.checkValidation(parentProfile);

                if (this.missingRequiredFields.length === 0) {
                    this.$emit('profile-save');
                }
            });
        },

        getParentProfileSelected() {
            return this.profileRepository.search(this.parentProfileCriteria).then((results) => {
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

        onCurrentPageChange(activePageIndex) {
            this.currentlyActivePage = activePageIndex;
        },

        onNextPage() {
            this.$refs.wizard.nextPage();
        },
    },
};
