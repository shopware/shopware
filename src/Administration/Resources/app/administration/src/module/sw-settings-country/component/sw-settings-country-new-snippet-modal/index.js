import template from './sw-settings-country-new-snippet-modal.html.twig';
import './sw-settings-country-new-snippet-modal.scss';

const { Component } = Shopware;
const utils = Shopware.Utils;

Component.register('sw-settings-country-new-snippet-modal', {
    template,

    props: {
        selections: {
            type: Array,
            required: false,
            default: () => [],
        },

        currentPosition: {
            type: Number,
            required: true,
        },

        advancedAddressFormat: {
            type: Array,
            required: true,
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            searchTerm: null,
            isLoading: false,
            searchResults: null,
            activeFocusId: null,
        };
    },

    computed: {
        selection() {
            return this.advancedAddressFormat[this.currentPosition];
        },
    },

    watch: {
        activeFocusId: {
            immediate: true,
            handler(value) {
                this.$route.params.snippet = value;
            },
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.getSnippetsTree(this.selections);
        },

        onCloseModal() {
            this.$emit('modal-close');
        },

        addElement({ data }) {
            this.advancedAddressFormat[this.currentPosition].push({
                label: data.name,
                value: data.id,
            });

            this.$emit('change', this.currentPosition, this.advancedAddressFormat[this.currentPosition]);
        },

        debouncedSearch: utils.debounce(function updateSnippets() {
            this.activeFocusId = null;

            if (!this.searchTerm) {
                this.getSnippetsTree(this.selections);
                return;
            }

            this.search();
        }, 750),

        search() {
            const keyWords = this.searchTerm.split(/[\W_]+/ig);

            const results = this.selections.filter(
                item => keyWords.every(key => item.name.toLowerCase().includes(key.toLowerCase())),
            );

            if (!results?.length) {
                return;
            }

            this.activeFocusId = results[0].id;
            this.getSnippetsTree(results);
        },

        getSnippetsTree(selections) {
            const mappedObj = {};

            const generate = (currentIndex, { keyWords, name }, result) => {
                const currentKey = keyWords[currentIndex];

                // next key is child of current key
                const nextKey = keyWords[currentIndex + 1];

                result[currentKey] = result[currentKey] || {
                    id: currentKey,
                    name,
                    parentId: null,
                    children: {},
                };

                if (!nextKey) {
                    return;
                }

                // Put next key into children of current key
                result[currentKey].children[nextKey] = result[currentKey].children[nextKey] || {
                    id: `${result[currentKey].id}.${nextKey}`,
                    name,
                    parentId: result[currentKey].id,
                    children: {},
                };

                generate(currentIndex + 1, { keyWords, name }, result[currentKey].children);
            };

            const convertTreeToArray = (nodes, output = []) => {
                const getName = ({ parentId = null, id, children, name }) => {
                    const [eventName] = parentId ? id.split('.').reverse() : [id];

                    // Replace '_' or '-' to blank space.
                    return !Object.values(children).length ? name : eventName.replace(/_|-/g, ' ');
                };

                nodes.forEach(node => {
                    const children = node.children ? Object.values(node.children) : [];
                    output.push({
                        id: node.id,
                        name: getName(node),
                        childCount: children.length,
                        parentId: node.parentId,
                    });

                    if (children.length > 0) {
                        output = convertTreeToArray(children, output);
                    }
                });
                return output;
            };

            selections.forEach(snippet => {
                const keyWords = snippet.id.split('.');
                if (keyWords.length === 0) {
                    return;
                }

                generate(0, { keyWords, ...snippet }, mappedObj);
            });

            this.searchResults = convertTreeToArray(Object.values(mappedObj));
        },

        onClickDismiss(index) {
            this.$emit(
                'change',
                this.currentPosition,
                this.advancedAddressFormat[this.currentPosition].filter((_, key) => key !== index),
            );
        },
    },
});
