import Sanitizer from 'src/core/helper/sanitizer.helper';
import template from './sw-snippet-field-edit-modal.html.twig';

const { Component } = Shopware;

/**
 * @status ready
 * @description The modal component used to edit snippet values in `<sw-snippet-field>`.
 * @example-type code-only
 * @component-example
 * <sw-snippet-field-edit-modal
 * :snippets="snippets"
 * :snippetSets="snippetSets"
 * :translationKey="snippet"
 * :fieldType="fieldType"
 * @modal-close="closeEditModal"
 * @save="onSave">
 * </sw-snippet-field-edit-modal>
 */
Component.register('sw-snippet-field-edit-modal', {
    template,

    inject: [
        'snippetService', // @deprecated tag:v6.4.0.0
        'acl'
    ],

    props: {
        snippets: {
            type: Array,
            required: true
        },

        snippetSets: {
            type: Array,
            required: true
        },

        translationKey: {
            type: String,
            required: true
        },

        fieldType: {
            type: String,
            required: true,
            validValues: ['text', 'textarea'],
            validator(value) {
                return ['text', 'textarea'].includes(value);
            }
        }
    },

    data() {
        return {
            isLoading: false,
            editableSnippets: []
        };
    },

    computed: {
        modalTitle() {
            const title = this.$tc('global.sw-snippet-field-edit-modal.title');

            return `${title}: ${this.translationKey}`;
        },

        currentAuthor() {
            return `user/${Shopware.State.get('session').currentUser.username}`;
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.isLoading = true;

            this.snippetSets.forEach((snippetSet) => {
                let existingSnippet = this.snippets.find(item => item.setId === snippetSet.id);

                if (!existingSnippet) {
                    existingSnippet = {
                        author: this.currentAuthor,
                        id: null,
                        value: null,
                        origin: null,
                        resetTo: '',
                        translationKey: this.translationKey,
                        setId: snippetSet.id
                    };
                }
                this.editableSnippets.push(existingSnippet);
            });

            this.isLoading = false;
        },

        closeModal() {
            this.$emit('modal-close');
        },

        getNoPermissionsTooltip(role) {
            return {
                showDelay: 300,
                appearance: 'dark',
                showOnDisabledElements: true,
                disabled: this.acl.can(role),
                message: this.$tc('sw-privileges.tooltip.warning')
            };
        },

        onSave() {
            const responses = [];
            this.isLoading = true;

            this.editableSnippets.forEach((snippet) => {
                snippet.value = Sanitizer.sanitize(snippet.value);
                snippet.author = this.currentAuthor;

                if (snippet.resetTo !== snippet.value) {
                    // Only save if value differs from original value
                    responses.push(this.snippetService.save(snippet));
                }
            });

            Promise.all(responses).then(() => {
                this.isLoading = false;
                this.$emit('save');
            });
        }
    }
});
