import template from './sw-language-switch.html.twig';
import './sw-language-switch.scss';

const { Component, StateDeprecated } = Shopware;
const { warn } = Shopware.Utils.debug;

/**
 * @public
 * @description
 * Renders a language switcher.
 * @status ready
 * @example-type code-only
 * @component-example
 * <sw-language-switch></sw-language-info>
 */
Component.register('sw-language-switch', {
    template,

    props: {
        disabled: {
            type: Boolean,
            required: false,
            default: false
        },
        changeGlobalLanguage: {
            type: Boolean,
            required: false,
            default: true
        },
        abortChangeFunction: {
            type: Function,
            required: false
        },
        saveChangesFunction: {
            type: Function,
            required: false
        }
    },

    data() {
        return {
            languageId: '',
            lastLanguageId: '',
            newLanguageId: '',
            showUnsavedChangesModal: false
        };
    },

    computed: {
        languageStore() {
            return StateDeprecated.getStore('language');
        }
    },

    created() {
        this.createdComponent();
    },

    destroyed() {
        this.destroyedComponent();
    },

    methods: {
        createdComponent() {
            this.languageId = this.languageStore.getCurrentId();
            this.lastLanguageId = this.languageId;
            this.$root.$on('on-change-language-clicked', this.changeToNewLanguage);
        },

        destroyedComponent() {
            this.$root.$off('on-change-language-clicked', this.changeToNewLanguage);
        },

        onInput() {
            this.newLanguageId = this.languageId;

            this.checkAbort();
        },

        checkAbort() {
            // Check if abort function exists und reset the select field if the change should be aborted
            if (typeof this.abortChangeFunction === 'function') {
                if (this.abortChangeFunction({
                    oldLanguageId: this.lastLanguageId,
                    newLanguageId: this.languageId
                })) {
                    this.showUnsavedChangesModal = true;
                    this.languageId = this.lastLanguageId;
                    this.$refs.languageSelect.loadSelected();
                    return;
                }
            }

            this.emitChange();
        },

        emitChange() {
            this.lastLanguageId = this.languageId;

            if (this.changeGlobalLanguage) {
                this.languageStore.setCurrentId(this.languageId);
                this.$root.$emit('on-change-application-language', { languageId: this.languageId });
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
            this.$refs.languageSelect.loadSelected();
            this.emitChange();
        }
    }
});
