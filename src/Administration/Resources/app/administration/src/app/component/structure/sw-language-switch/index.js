import template from './sw-language-switch.html.twig';
import './sw-language-switch.scss';
import 'src/app/store/admin-reference-data.store';

const { warn } = Shopware.Utils.debug;

/**
 * @sw-package framework
 *
 * @private
 * @description
 * Renders a language switcher.
 * @status ready
 * @example-type code-only
 * @component-example
 * <sw-language-switch></sw-language-switch>
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    emits: ['on-change'],

    props: {
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
        changeGlobalLanguage: {
            type: Boolean,
            required: false,
            default: true,
        },
        abortChangeFunction: {
            type: Function,
            required: false,
            default: () => {},
        },
        saveChangesFunction: {
            type: Function,
            required: false,
            default: () => {},
        },
        savePermission: {
            type: Boolean,
            required: false,
            default: true,
        },
        allowEdit: {
            type: Boolean,
            required: false,
            default: true,
        },
    },

    data() {
        return {
            languageId: '',
            lastLanguageId: '',
            newLanguageId: '',
            activeLanguages: [],
            isLoadingLanguages: false,
            showUnsavedChangesModal: false,
        };
    },

    created() {
        this.createdComponent();
    },

    unmounted() {
        this.destroyedComponent();
    },

    methods: {
        createdComponent() {
            this.languageId = Shopware.Context.api.languageId;
            this.lastLanguageId = this.languageId;

            this.loadActiveLanguages();

            Shopware.Utils.EventBus.on('on-change-language-clicked', this.changeToNewLanguage);
        },

        destroyedComponent() {
            Shopware.Utils.EventBus.off('on-change-language-clicked', this.changeToNewLanguage);
        },

        loadActiveLanguages() {
            this.isLoadingLanguages = true;

            return Shopware.Store.get('adminReferenceData')
                .loadActiveLanguages()
                .then((languages) => {
                    this.activeLanguages = [...languages];

                    return languages;
                })
                .finally(() => {
                    this.isLoadingLanguages = false;
                });
        },

        onInput(newLanguageId) {
            this.languageId = newLanguageId;
            this.newLanguageId = newLanguageId;

            this.checkAbort();
        },

        checkAbort() {
            // Check if abort function exists und reset the select field if the change should be aborted
            if (typeof this.abortChangeFunction === 'function' && this.savePermission) {
                if (
                    this.abortChangeFunction({
                        oldLanguageId: this.lastLanguageId,
                        newLanguageId: this.languageId,
                    })
                ) {
                    this.showUnsavedChangesModal = true;
                    this.languageId = this.lastLanguageId;

                    return;
                }
            }

            this.emitChange();
        },

        emitChange() {
            this.lastLanguageId = this.languageId;

            if (this.changeGlobalLanguage) {
                Shopware.Store.get('context').api.languageId = this.languageId;
                Shopware.Utils.EventBus.emit('sw-language-switch-change-application-language', {
                    languageId: this.languageId,
                });
            }

            this.$emit('on-change', this.languageId);
        },

        onCloseChangesModal() {
            this.showUnsavedChangesModal = false;
            this.newLanguageId = '';
        },

        onClickSaveChanges() {
            let save = {};
            // Check if save function exists and wait for it before changing the language
            if (typeof this.saveChangesFunction === 'function') {
                save = this.saveChangesFunction();
            } else {
                warn('sw-language-switch', 'You need to implement an own save function to save the changes!');
            }
            return Promise.resolve(save).then(() => {
                this.changeToNewLanguage();
                this.onCloseChangesModal();
            });
        },

        onClickRevertUnsavedChanges() {
            this.changeToNewLanguage();
            this.onCloseChangesModal();
        },

        changeToNewLanguage(languageId) {
            if (languageId) {
                this.newLanguageId = languageId;
            }
            this.languageId = this.newLanguageId;
            this.newLanguageId = '';
            this.emitChange();
        },
    },
};
