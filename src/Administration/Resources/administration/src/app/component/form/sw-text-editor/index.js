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
            type: String,
            required: false,
            default: ''
        },

        placeholder: {
            type: String,
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
                        type: 'foreColor',
                        value: ''
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
            isEmpty: false,
            hasSelection: false,
            selection: null,
            currentSelection: null,
            toolbar: null,
            textLength: 0,
            content: '',
            placeholderHeight: null,
            placeholderVisible: false
        };
    },

    computed: {
        classes() {
            return {
                'is--active': this.isActive,
                'is--boxed': !this.isInlineEdit,
                'is--empty': this.isEmpty
            };
        }
    },

    watch: {
        value: {
            handler() {
                if (this.value !== this.$refs.editor.innerHTML) {
                    this.content = this.value;
                    this.isEmpty = this.emptyCheck(this.content);
                }

                this.$nextTick(() => {
                    this.setWordCount();
                });
            }
        }
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

    methods: {
        createdComponent() {
            this.content = this.value;

            document.addEventListener('mouseup', this.onSelectionChange);
            document.addEventListener('mousedown', this.onSelectionChange);
        },

        mountedComponent() {
            this.isEmpty = this.emptyCheck(this.content);
            this.placeholderVisible = !this.isEmpty;

            this.$nextTick(() => {
                this.setWordCount();
            });
        },

        destroyedComponent() {
            document.removeEventListener('mouseup', this.onSelectionChange);
            document.removeEventListener('mousedown', this.onSelectionChange);
        },

        onSelectionChange(event) {
            const path = this.getPath(event);

            if (event.type === 'mousedown' && !path.includes(this.$el) && !path.includes(this.toolbar)) {
                this.hasSelection = false;
                return;
            }

            if (!this.isActive) {
                return;
            }

            if (path.includes(this.toolbar)) {
                return;
            }

            if (event.type === 'mousedown') {
                document.getSelection().empty();
            }

            this.resetForeColor();
            this.hasSelection = !!document.getSelection().toString();
            this.selection = document.getSelection();
        },

        getPath(event) {
            const path = [];
            let source = event.target;
            while (source) {
                path.push(source);
                source = source.parentNode;
            }

            return path;
        },

        resetForeColor() {
            Object.keys(this.buttonConfig).forEach(
                (key) => {
                    if (this.buttonConfig[key].type === 'foreColor') {
                        this.buttonConfig[key].value = '';
                    }
                }
            );
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
                document.addEventListener('mousedown', this.onDocumentClick);
                this.isActive = true;
                this.placeholderVisible = false;
            }
        },

        removeFocus() {
            if (!this.isActive) {
                return;
            }

            if (this.$refs.editor.innerHTML.length <= 0) {
                this.placeholderVisible = true;
            }

            this.isActive = false;
            this.emitContent();
            document.removeEventListener('mousedown', this.onDocumentClick);
        },

        onDocumentClick(event) {
            const path = this.getPath(event);
            if (path.includes(this.toolbar)) {
                return;
            }

            if (!path.includes(this.$el)) {
                this.removeFocus();
            }
        },

        onContentChange() {
            this.isEmpty = this.emptyCheck(this.getContentValue());

            this.setWordCount();
        },

        onPaste(event) {
            event.preventDefault();

            const clipboardData = event.clipboardData || window.clipboardData;
            const text = clipboardData.getData('text/plain');
            document.execCommand('insertHTML', false, text);
        },

        emitContent() {
            this.$emit('input', this.getContentValue());
        },

        getContentValue() {
            if (!this.$refs.editor || !this.$refs.editor.innerHTML) {
                return null;
            }

            if (!this.$refs.editor.textContent ||
                !this.$refs.editor.textContent.length ||
                this.$refs.editor.textContent.length <= 0) {
                return null;
            }

            return this.$refs.editor.innerHTML;
        },

        emptyCheck(value) {
            return !value || value === null || !value.length || value.length <= 0;
        },

        setWordCount() {
            this.textLength = this.$refs.editor.innerText.length;
        }
    }
};
