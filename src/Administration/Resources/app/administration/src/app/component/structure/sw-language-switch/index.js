import template from './sw-language-switch.html.twig';
import './sw-language-switch.scss';

const { Component } = Shopware;
const { warn } = Shopware.Utils.debug;
const { Criteria } = Shopware.Data;

/**
 * @public
 * @description
 * Renders a language switcher.
 * @status ready
 * @example-type code-only
 * @component-example
 * <sw-language-switch></sw-language-switch>
 */
Component.register('sw-language-switch', {
    template,

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
            showUnsavedChangesModal: false,
        };
    },

    computed: {
        languageCriteria() {
            const criteria = new Criteria();

            criteria.addSorting(Criteria.sort('name', 'ASC', false));

            return criteria;
        },
    },

    created() {
        this.createdComponent();
    },

    destroyed() {
        this.destroyedComponent();
    },

    methods: {
        createdComponent() {
            this.languageId = Shopware.Context.api.languageId;
            this.lastLanguageId = this.languageId;
            this.$root.$on('on-change-language-clicked', this.changeToNewLanguage);
        },

        destroyedComponent() {
            this.$root.$off('on-change-language-clicked', this.changeToNewLanguage);
        },

        onInput(newLanguageId) {
            this.languageId = newLanguageId;
            this.newLanguageId = newLanguageId;

            this.checkAbort();
        },

        checkAbort() {
            // Check if abort function exists und reset the select field if the change should be aborted
            if (typeof this.abortChangeFunction === 'function' && this.savePermission) {
                if (this.abortChangeFunction({
                    oldLanguageId: this.lastLanguageId,
                    newLanguageId: this.languageId,
                })) {
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
                Shopware.State.commit('context/setApiLanguageId', this.languageId);
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
            this.emitChange();
        },
    },
});
