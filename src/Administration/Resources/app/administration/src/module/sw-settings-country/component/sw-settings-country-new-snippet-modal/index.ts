import type { PropType } from 'vue';
import template from './sw-settings-country-new-snippet-modal.html.twig';
import './sw-settings-country-new-snippet-modal.scss';

const { Component } = Shopware;
const utils = Shopware.Utils;

interface Selection {
    id: string,
    name: string,
    parentId?: string | null,
}

interface TreeItem {
    id: string,
    name: string,
    parentId?: string | null,
    childCount?: number,
    children: {
        [key: string]: TreeItem
    }
}

/**
 * @package customer-order
 *
 * @private
 */
Component.register('sw-settings-country-new-snippet-modal', {
    template,

    props: {
        selections: {
            type: Array as PropType<Selection[]>,
            required: false,
            default: () => [],
        },

        currentPosition: {
            type: Number,
            required: true,
        },

        addressFormat: {
            type: Array as PropType<Array<string[]>>,
            required: true,
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },

        getLabelProperty: {
            type: Function,
            required: false,
            default: (value: string) => value,
        },
    },

    data(): {
        searchTerm: string,
        isLoading: boolean,
        searchResults: TreeItem[] | null,
        activeFocusId: string | null,
        } {
        return {
            searchTerm: '',
            isLoading: false,
            searchResults: null,
            activeFocusId: null,
        };
    },

    computed: {
        selection(): string[] {
            return this.addressFormat[this.currentPosition];
        },
    },

    watch: {
        activeFocusId: {
            immediate: true,
            handler(value) {
                // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
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

        addElement(data: Selection) {
            this.addressFormat[this.currentPosition].push(data.id.replace('.', '/'));

            this.$emit('change', this.currentPosition, this.addressFormat[this.currentPosition]);
        },

        debouncedSearch: utils.debounce(function updateSnippets(this: $TSFixMe): void {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
            if (!this.searchTerm) {
                // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
                this.getSnippetsTree(this.selections);
                return;
            }

            // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
            this.search();
        }, 750),

        search(): void {
            this.activeFocusId = null;

            const keyWords = this.searchTerm.split(/[\W_]+/ig);

            if (!keyWords) {
                return;
            }

            const results = this.selections.filter(
                item => keyWords.every(key => item.name.toLowerCase().includes(key.toLowerCase())),
            );

            if (results.length === 0) {
                return;
            }

            this.activeFocusId = results[0].id;
            this.getSnippetsTree(results);
        },

        getSnippetsTree(selections: Selection[]): void {
            const mappedObj = {};

            const generate = (
                currentIndex: number,
                argument: { keyWords: string[], name: string },
                result: { [key: string]: TreeItem },
            ) => {
                const { keyWords, name } = argument;
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
                result[currentKey].children[nextKey] = result[currentKey]?.children[nextKey] || {
                    id: `${result[currentKey].id}.${nextKey}`,
                    name,
                    parentId: result[currentKey].id,
                    children: {},
                };

                generate(currentIndex + 1, { keyWords, name }, result[currentKey].children);
            };

            const convertTreeToArray = (nodes: TreeItem[], output: TreeItem[] = []) => {
                const getName = ({ parentId = null, id, children, name }: TreeItem): string => {
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
                        children: {},
                    });

                    if (children.length > 0) {
                        output = convertTreeToArray(children, output);
                    }
                });

                return output;
            };

            selections.forEach(snippet => {
                const keyWords = snippet.id.split('/');
                if (keyWords.length === 0) {
                    return;
                }

                generate(0, { keyWords, ...snippet }, mappedObj);
            });

            this.searchResults = convertTreeToArray(Object.values(mappedObj));
        },

        onClickDismiss(index: number) {
            this.$emit(
                'change',
                this.currentPosition,
                this.addressFormat[this.currentPosition].filter((_, key) => key !== index),
            );
        },
    },
});
