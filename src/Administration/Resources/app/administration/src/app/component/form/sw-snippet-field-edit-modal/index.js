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
        // @Jonas no usage found, still relevant?
        'acl',
        'repositoryFactory',
    ],

    props: {
        snippets: {
            type: Array,
            required: true,
        },

        snippetSets: {
            type: Array,
            required: true,
        },

        translationKey: {
            type: String,
            required: true,
        },

        fieldType: {
            type: String,
            required: true,
            validValues: ['text', 'textarea'],
            validator(value) {
                return ['text', 'textarea'].includes(value);
            },
        },
    },

    data() {
        return {
            isLoading: false,
            editableSnippets: [],
        };
    },

    computed: {
        modalTitle() {
            const title = this.$tc('global.sw-snippet-field-edit-modal.title');

            return `${title}: ${this.translationKey}`;
        },

        currentAuthor() {
            return `user/${Shopware.State.get('session').currentUser.username}`;
        },

        snippetRepository() {
            return this.repositoryFactory.create('snippet');
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.isLoading = true;

            this.snippetSets.forEach((snippetSet) => {
                const existingSnippet = this.snippets.find(item => item.setId === snippetSet.id);
                const snippet = this.snippetRepository.create(Shopware.Context.api);

                if (existingSnippet) {
                    snippet.author = existingSnippet.author;
                    snippet.id = existingSnippet.id;
                    snippet.value = existingSnippet.value;
                    snippet.origin = existingSnippet.origin;
                    snippet.translationKey = existingSnippet.translationKey;
                    snippet.setId = existingSnippet.setId;

                    if (existingSnippet.id) {
                        snippet._isNew = false;
                    }
                } else {
                    snippet.author = this.currentAuthor;
                    snippet.id = null;
                    snippet.value = null;
                    snippet.origin = null;
                    snippet.translationKey = this.translationKey;
                    snippet.setId = snippetSet.id;
                }

                this.editableSnippets.push(snippet);
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
                message: this.$tc('sw-privileges.tooltip.warning'),
            };
        },

        onSave() {
            const responses = [];
            this.isLoading = true;

            this.editableSnippets.forEach((snippet) => {
                snippet.value = Sanitizer.sanitize(snippet.value);
                snippet.author = this.currentAuthor;

                if (!snippet.hasOwnProperty('value') || snippet.value === '') {
                    // If you clear the input-box, reset it to its origin value
                    snippet.value = snippet.origin;
                }

                if (snippet.origin !== snippet.value) {
                    // Only save if values differs from origin
                    responses.push(
                        this.snippetRepository.save(snippet, Shopware.Context.api),
                    );
                } else if (snippet.hasOwnProperty('id') && snippet.id !== null) {
                    // There's no need to keep a snippet which is exactly like the file-snippet, so delete
                    responses.push(
                        this.snippetRepository.delete(snippet.id, Shopware.Context.api),
                    );
                }
            });

            Promise.all(responses).then(() => {
                this.isLoading = false;
                this.$emit('save');
            });
        },
    },
});
