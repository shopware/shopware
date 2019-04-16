import template from './sw-text-editor.html.twig';
import './sw-text-editor.scss';

/**
 * @public
 * @status ready
 * @example-type static
 * @description A simple text editor which uses the browsers api, pass buttonConfig to configure the buttons you desire
 * @component-example
 * <div>
 *      <sw-text-editor-new value="Lorem ipsum dolor sit amet, consetetur sadipscing elitr" :isInlineEdit="true">
 *
 *      </sw-text-editor-new>
 *
 *      <sw-text-editor-new
 *          value="Lorem ipsum dolor sit amet, consetetur sadipscing elitr"
 *          :buttonConfig="[{ type: 'bold', icon: 'default-text-editor-bold' },
 *                          {
 *                            type: 'paragparh',
 *                            icon: 'default-text-editor-style',
 *                            expanded: false,
 *                            children: [
 *                                {
 *                                   type: 'formatBlock',
 *                                   name: 'Paragraph',
 *                                   value: 'p'
 *                                },
 *                                {
 *                                   type: 'formatBlock',
 *                                   name: 'Headline 1',
 *                                   value: 'h1'
 *                                }
 *                             ]
 *                           },
 *                           {
 *                               type: 'link',
 *                               icon: 'default-text-editor-link',
 *                               expanded: false,
 *                               newTab: false,
 *                               value: ''
 *                           }
 *                         ]">
 *
 *      </sw-text-editor-new>
 * </div>
 */
export default {
    // ToDo: refactor to sw-text-editor and replace old editor
    name: 'sw-text-editor-new',
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

        buttonConfig: {
            type: Array,
            required: false,
            default() {
                return [
                    {
                        type: 'bold',
                        icon: 'default-text-editor-bold'
                    },
                    {
                        type: 'italic',
                        icon: 'default-text-style-italic'
                    },
                    {
                        type: 'underline',
                        icon: 'default-text-editor-underline'
                    },
                    {
                        type: 'insertOrderedList',
                        icon: 'default-text-editor-list'
                    },
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
            toolbar: null
        };
    },

    created() {
        this.createdComponent();
    },

    destroyed() {
        this.destroyedComponent();
    },

    computed: {
        classes() {
            return {
                'is--active': this.isActive,
                'is--boxed': !this.isInlineEdit
            };
        }
    },

    methods: {
        createdComponent() {
            document.addEventListener('mouseup', this.onSelectionChange);
            document.addEventListener('mousedown', this.onSelectionChange);
        },

        destroyedComponent() {
            document.removeEventListener('mouseup', this.onSelectionChange);
            document.removeEventListener('mousedown', this.onSelectionChange);
        },

        onSelectionChange(event) {
            if (!event.path.includes(this.$el) || !this.isActive) {
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
        },

        onSetLink(value, target) {
            if (!this.selection.toString()) {
                return;
            }

            document.execCommand('insertHTML', false, `<a target="${target}" href="${value}">${this.selection}</a>`);

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
            if (this.isActive) {
                this.isActive = false;
                document.removeEventListener('click', this.onDocumentClick);
            }
        },

        onDocumentClick(event) {
            if (event.path.includes(this.toolbar)) {
                return;
            }

            if (!event.path.includes(this.$el)) {
                this.removeFocus();
            }
        }
    }
};
