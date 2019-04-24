import { Mixin } from 'src/core/shopware';
import template from './sw-text-editor.html.twig';
import './sw-text-editor.scss';

/**
 * @public
 * @status ready
 * @example-type static
 * @description A simple text editor which uses the browsers api, pass buttonConfig to configure the buttons you desire
 * @component-example
 *  <sw-text-editor value="Lorem ipsum dolor sit amet, consetetur sadipscing elitr" :isInlineEdit="true">
 *
 *  </sw-text-editor>
 */
export default {
    name: 'sw-text-editor',
    template,

    mixins: [
        Mixin.getByName('sw-inline-snippet')
    ],

    props: {
        value: {
            type: String,
            required: false,
            default: ''
        },

        isInlineEdit: {
            type: Boolean,
            required: false,
            default: false
        },

        label: {
            required: false,
            default: ''
        },

        placeholder: {
            required: false,
            default: ''
        },

        buttonConfig: {
            type: Array,
            required: false,
            default() {
                return [
                    {
                        type: 'paragparh',
                        icon: 'default-text-editor-style',
                        expanded: false,
                        children: [
                            {
                                type: 'formatBlock',
                                name: 'Paragraph',
                                value: 'p'
                            },
                            {
                                type: 'formatBlock',
                                name: 'Heading 1',
                                value: 'h1'
                            },
                            {
                                type: 'formatBlock',
                                name: 'Heading 2',
                                value: 'h2'
                            },
                            {
                                type: 'formatBlock',
                                name: 'Heading 3',
                                value: 'h3'
                            },
                            {
                                type: 'formatBlock',
                                name: 'Heading 4',
                                value: 'h4'
                            },
                            {
                                type: 'formatBlock',
                                name: 'Heading 5',
                                value: 'h5'
                            },
                            {
                                type: 'formatBlock',
                                name: 'Heading 6',
                                value: 'h6'
                            },
                            {
                                type: 'formatBlock',
                                name: 'Blockquote',
                                value: 'blockquote'
                            }
                        ]
                    },
                    {
                        type: 'bold',
                        icon: 'default-text-editor-bold'
                    },
                    {
                        type: 'italic',
                        icon: 'default-text-editor-italic'
                    },
                    {
                        type: 'underline',
                        icon: 'default-text-editor-underline'
                    },
                    {
                        type: 'strikethrough',
                        icon: 'default-text-editor-strikethrough'
                    },
                    {
                        type: 'superscript',
                        icon: 'default-text-editor-superscript'
                    },
                    {
                        type: 'subscript',
                        icon: 'default-text-editor-subscript'
                    },
                    {
                        type: 'justify',
                        icon: 'default-text-editor-align-left',
                        expanded: false,
                        children: [
                            {
                                type: 'justifyLeft',
                                icon: 'default-text-align-left'
                            },
                            {
                                type: 'justifyCenter',
                                icon: 'default-text-align-center'
                            },
                            {
                                type: 'justifyRight',
                                icon: 'default-text-align-right'
                            },
                            {
                                type: 'justifyFull',
                                icon: 'default-text-align-justify'
                            }
                        ]
                    },
                    {
                        type: 'insertUnorderedList',
                        icon: 'default-text-editor-list-unordered'
                    },
                    {
                        type: 'insertOrderedList',
                        icon: 'default-text-editor-list-numberd'
                    },
                    {
                        type: 'link',
                        icon: 'default-text-editor-link',
                        expanded: false,
                        newTab: false,
                        value: ''
                    },
                    {
                        type: 'undo',
                        icon: 'default-text-editor-undo'
                    },
                    {
                        type: 'redo',
                        icon: 'default-text-editor-redo'
                    }
                ];
            }
        }
    },

    data() {
        return {
            isActive: false,
            hasSelection: false,
            selection: null,
            toolbar: null,
            textLength: 0,
            content: this.value
        };
    },

    created() {
        this.createdComponent();
    },

    mounted() {
        this.mountedComponent();
    },

    destroyed() {
        this.destroyedComponent();
    },

    watch: {
        value: {
            handler() {
                if (this.value && !this.isActive) {
                    this.content = this.value;
                    this.$nextTick(() => {
                        this.setWordcount();
                    });
                } else {
                    this.setWordcount();
                }
            }
        }
    },

    computed: {
        classes() {
            return {
                'is--active': this.isActive,
                'is--boxed': !this.isInlineEdit
            };
        },

        placeholderVisible() {
            return this.textLength === 0;
        }
    },

    methods: {
        createdComponent() {
            document.addEventListener('mouseup', this.onSelectionChange);
            document.addEventListener('mousedown', this.onSelectionChange);
        },

        mountedComponent() {
            if (this.value) {
                this.setWordcount();
            }
        },

        destroyedComponent() {
            document.removeEventListener('mouseup', this.onSelectionChange);
            document.removeEventListener('mousedown', this.onSelectionChange);
        },

        onSelectionChange(event) {
            if (event.type === 'mousedown' && !event.path.includes(this.$el) && !event.path.includes(this.toolbar)) {
                this.hasSelection = false;
                return;
            }

            if (!this.isActive) {
                return;
            }

            if (event.path.includes(this.toolbar)) {
                return;
            }

            if (event.type === 'mousedown') {
                document.getSelection().empty();
            }

            this.hasSelection = !!document.getSelection().toString();
            this.selection = document.getSelection();
        },

        onToolbarCreated(elem) {
            this.toolbar = elem;
        },

        onToolbarDestroyed() {
            this.toolbar = null;
        },

        onTextStyleChange(type, value) {
            document.execCommand(type, false, value);
            this.emitContent();
        },

        onSetLink(value, target) {
            if (!this.selection.toString()) {
                return;
            }

            this.onTextStyleChange('insertHTML', `<a target="${target}" href="${value}">${this.selection}</a>`);
            this.selection = document.getSelection();
        },

        onClick() {
            this.isActive = true;
        },

        onFocus() {
            this.setFocus();
            document.execCommand('defaultParagraphSeparator', false, 'span');
        },

        setFocus() {
            if (!this.isActive) {
                document.addEventListener('click', this.onDocumentClick);
                this.isActive = true;
            }
        },

        removeFocus() {
            if (!this.isActive) {
                return;
            }

            this.isActive = false;
            this.emitContent();
            document.removeEventListener('click', this.onDocumentClick);
        },

        onDocumentClick(event) {
            if (event.path.includes(this.toolbar)) {
                return;
            }

            if (!event.path.includes(this.$el)) {
                this.removeFocus();
            }
        },

        onContentChange() {
            this.emitContent();
        },

        emitContent() {
            if (!this.$refs.editor || this.value === this.$refs.editor.innerHTML) {
                return;
            }


            // remove leading and trailing <br>
            const regex = /^\s*(?:<br\s*\/?\s*>)+|(?:<br\s*\/?\s*>)+\s*$/gi;
            let val = this.$refs.editor.innerHTML.replace(regex, '');

            val = !val ? null : val;

            this.$emit('input', val);
        },

        setWordcount() {
            // strip line breaks
            let text = this.$refs.editor.innerText.replace(/(\r\n|\n|\r)/gm, '');

            // strip Tags
            text = text.replace(/<\/?("[^"]*"|'[^']*'|[^>])*(>|$)/g, '');
            this.textLength = text.length;
        }
    }
};
