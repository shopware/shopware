import template from './sw-text-editor.html.twig';
import './sw-text-editor.scss';

/**
 * @public
 * @status ready
 * @example-type static
 * @description A simple text editor which uses the browsers api.
 *              Pass a buttonConfig array to configure the buttons you desire.
 *              Each Button needs to be an object with a type (this will be the executed Command as well),
 *              a name or an icon which will be displayed as the button and
 *              the created HTML-Tag (this is needed to set actives states in the Toolbar).
 *              If the type requires a value you can set the value prop,
 *              which will be passed in the execCommand function.
 *              To read more about the execCommand function see
 *              https://developer.mozilla.org/de/docs/Web/API/Document/execCommand.
 *
 *              If you want to generate a sub-menu you can set a children prop in the button-object which,
 *              holds the buttonConfig of the children (Button syntax is the same as explained above).
 *
 *              If you need to call a custom callback instead you can pass your handler with a handler prop
 *              e.g. handler: (button, parent = null) => { callback(button, parent) }
 *
 *              Furthermore you can pass the position prop [left (default), middle and right]
 *              to set the buttons position in the toolbar.
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

        disabled: {
            type: Boolean,
            required: false,
            default: false
        },

        buttonConfig: {
            type: Array,
            required: false,
            default() {
                return [
                    {
                        type: 'paragparh',
                        icon: 'default-text-editor-style',
                        children: [
                            {
                                type: 'formatBlock',
                                name: 'Paragraph',
                                value: 'p',
                                tag: 'p'
                            },
                            {
                                type: 'formatBlock',
                                name: 'Heading 1',
                                value: 'h1',
                                tag: 'h1'
                            },
                            {
                                type: 'formatBlock',
                                name: 'Heading 2',
                                value: 'h2',
                                tag: 'h2'
                            },
                            {
                                type: 'formatBlock',
                                name: 'Heading 3',
                                value: 'h3',
                                tag: 'h3'
                            },
                            {
                                type: 'formatBlock',
                                name: 'Heading 4',
                                value: 'h4',
                                tag: 'h4'
                            },
                            {
                                type: 'formatBlock',
                                name: 'Heading 5',
                                value: 'h5',
                                tag: 'h5'
                            },
                            {
                                type: 'formatBlock',
                                name: 'Heading 6',
                                value: 'h6',
                                tag: 'h6'
                            },
                            {
                                type: 'formatBlock',
                                name: 'Blockquote',
                                value: 'blockquote',
                                tag: 'blockquote'
                            }
                        ]
                    },
                    {
                        type: 'foreColor',
                        value: '',
                        tag: 'font'
                    },
                    {
                        type: 'bold',
                        icon: 'default-text-editor-bold',
                        tag: 'b'
                    },
                    {
                        type: 'italic',
                        icon: 'default-text-editor-italic',
                        tag: 'i'
                    },
                    {
                        type: 'underline',
                        icon: 'default-text-editor-underline',
                        tag: 'u'
                    },
                    {
                        type: 'strikethrough',
                        icon: 'default-text-editor-strikethrough',
                        tag: 'strike'
                    },
                    {
                        type: 'superscript',
                        icon: 'default-text-editor-superscript',
                        tag: 'sup'
                    },
                    {
                        type: 'subscript',
                        icon: 'default-text-editor-subscript',
                        tag: 'sub'
                    },
                    {
                        type: 'justify',
                        icon: 'default-text-editor-align-left',
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
                        icon: 'default-text-editor-list-unordered',
                        tag: 'ul'
                    },
                    {
                        type: 'insertOrderedList',
                        icon: 'default-text-editor-list-numberd',
                        tag: 'ol'
                    },
                    {
                        type: 'link',
                        icon: 'default-text-editor-link',
                        expanded: false,
                        newTab: false,
                        value: '',
                        tag: 'a'
                    },
                    {
                        type: 'undo',
                        icon: 'default-text-editor-undo',
                        position: 'middle'
                    },
                    {
                        type: 'redo',
                        icon: 'default-text-editor-redo',
                        position: 'middle'
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
            placeholderVisible: false,
            isCodeEdit: false
        };
    },

    computed: {
        classes() {
            return {
                'is--active': this.isActive,
                'is--disabled': this.disabled,
                'is--boxed': !this.isInlineEdit,
                'is--empty': this.isEmpty
            };
        }
    },

    watch: {
        value: {
            handler() {
                if (this.$refs.textEditor && this.value !== this.$refs.textEditor.innerHTML) {
                    this.content = this.value;
                    this.isEmpty = this.emptyCheck(this.content);
                    this.placeholderVisible = this.isEmpty;
                }

                this.$nextTick(() => {
                    this.setWordCount();
                });
            }
        },

        isCodeEdit() {
            if (!this.isCodeEdit) {
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

            if (!this.isInlineEdit && !this.$options.buttonConfig) {
                this.buttonConfig.push({
                    type: 'codeSwitch',
                    icon: 'default-text-editor-code',
                    expanded: this.isCodeEdit,
                    handler: this.toggleCodeEditor,
                    position: 'right'
                });
            }

            document.addEventListener('mouseup', this.onSelectionChange);
            document.addEventListener('mousedown', this.onSelectionChange);
            document.addEventListener('keydown', this.onSelectionChange);
        },

        toggleCodeEditor(buttonConf) {
            this.isCodeEdit = !this.isCodeEdit;
            buttonConf.expanded = !buttonConf.expanded;
        },

        mountedComponent() {
            this.isEmpty = this.emptyCheck(this.content);
            this.placeholderVisible = this.isEmpty;

            this.$nextTick(() => {
                this.setWordCount();
            });
        },

        destroyedComponent() {
            document.removeEventListener('mouseup', this.onSelectionChange);
            document.removeEventListener('mousedown', this.onSelectionChange);
            document.removeEventListener('keydown', this.onSelectionChange);
        },

        onSelectionChange(event) {
            if (this.isCodeEdit || !this.isActive) {
                return;
            }

            const path = this.getPath(event);

            if ((event.type === 'keydown' || event.type === 'mousedown') &&
                !path.includes(this.$el) && !path.includes(this.toolbar)) {
                this.hasSelection = false;
                return;
            }

            if (path.includes(this.toolbar)) {
                return;
            }

            if (event.type === 'mousedown') {
                document.getSelection().empty();
                this.resetForeColor();
            }

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

            if (this.$refs.textEditor && this.$refs.textEditor.innerHTML.length <= 0) {
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
            const text = clipboardData.getData('text');
            document.execCommand('insertText', false, text);
        },

        emitContent() {
            this.$emit('input', this.getContentValue());
        },

        emitHtmlContent(value) {
            this.content = value;
            this.$emit('input', value);
        },

        getContentValue() {
            if (!this.$refs.textEditor || !this.$refs.textEditor.innerHTML) {
                return null;
            }

            if (!this.$refs.textEditor.textContent ||
                !this.$refs.textEditor.textContent.length ||
                this.$refs.textEditor.textContent.length <= 0) {
                return null;
            }

            return this.$refs.textEditor.innerHTML;
        },

        emptyCheck(value) {
            return !value || value === null || !value.length || value.length <= 0;
        },

        setWordCount() {
            if (this.$refs.textEditor) {
                this.textLength = this.$refs.textEditor.innerText.length;
            }
        }
    }
};
