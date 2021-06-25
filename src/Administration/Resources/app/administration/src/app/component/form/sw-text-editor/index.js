import template from './sw-text-editor.html.twig';
import './sw-text-editor.scss';

const { Component } = Shopware;

/**
 * @public
 * @status ready
 * @example-type static
 * @description <p>A simple text editor which uses the browsers api.
 *              Pass a buttonConfig array to configure the buttons you desire.
 *              Each Button needs to be an object with a type (this will be the executed Command as well),
 *              a name or an icon which will be displayed as the button and
 *              the created HTML-Tag (this is needed to set actives states in the Toolbar).
 *              If the type requires a value you can set the value prop,
 *              which will be passed in the execCommand function.</p>
 *              <p>To read more about the execCommand function see</p>
 *              <a href="https://developer.mozilla.org/de/docs/Web/API/Document/execCommand" target="_blank" rel="noopener">
 *              https://developer.mozilla.org/de/docs/Web/API/Document/execCommand</a>
 *
 *              <p>If you want to generate a sub-menu you can set a children prop in the button-object which,
 *              holds the buttonConfig of the children (Button syntax is the same as explained above).</p>
 *
 *              <p>If you need to call a custom callback instead you can pass your handler with a handler prop
 *              e.g. handler: (button, parent = null) => { callback(button, parent) }</p>
 *
 *              <p>Furthermore you can pass the position prop [left (default), middle and right]
 *              to set the buttons position in the toolbar.</p>
 * @component-example
 *  <sw-text-editor
 *      value="Lorem ipsum dolor sit amet, consetetur sadipscing elitr"
 *      :is-inline-edit="true"
 *  />
 */
Component.register('sw-text-editor', {
    template,

    inject: ['feature'],

    props: {
        value: {
            type: String,
            required: false,
            default: '',
        },

        isInlineEdit: {
            type: Boolean,
            required: false,
            default: false,
        },

        verticalAlign: {
            type: String,
            required: false,
            default: '',
            validValues: ['', 'center', 'flex-start', 'flex-end'],
            validator(value) {
                return ['', 'center', 'flex-start', 'flex-end'].includes(value);
            },
        },

        label: {
            type: String,
            required: false,
            default: '',
        },

        placeholder: {
            type: String,
            required: false,
            default: '',
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },

        allowInlineDataMapping: {
            type: Boolean,
            required: false,
            default: false,
        },

        sanitizeInput: {
            type: Boolean,
            required: false,
            default: false,
        },

        sanitizeFieldName: {
            type: String,
            required: false,
            default: null,
        },

        buttonConfig: {
            type: Array,
            required: false,
            default() {
                return [
                    {
                        type: 'paragraph',
                        title: this.$tc('sw-text-editor-toolbar.title.format'),
                        icon: 'default-text-editor-style',
                        children: [
                            {
                                type: 'formatBlock',
                                name: this.$tc('sw-text-editor-toolbar.title.paragraph'),
                                value: 'p',
                                tag: 'p',
                            },
                            {
                                type: 'formatBlock',
                                name: this.$tc('sw-text-editor-toolbar.title.h1'),
                                value: 'h1',
                                tag: 'h1',
                            },
                            {
                                type: 'formatBlock',
                                name: this.$tc('sw-text-editor-toolbar.title.h2'),
                                value: 'h2',
                                tag: 'h2',
                            },
                            {
                                type: 'formatBlock',
                                name: this.$tc('sw-text-editor-toolbar.title.h3'),
                                value: 'h3',
                                tag: 'h3',
                            },
                            {
                                type: 'formatBlock',
                                name: this.$tc('sw-text-editor-toolbar.title.h4'),
                                value: 'h4',
                                tag: 'h4',
                            },
                            {
                                type: 'formatBlock',
                                name: this.$tc('sw-text-editor-toolbar.title.h5'),
                                value: 'h5',
                                tag: 'h5',
                            },
                            {
                                type: 'formatBlock',
                                name: this.$tc('sw-text-editor-toolbar.title.h6'),
                                value: 'h6',
                                tag: 'h6',
                            },
                            {
                                type: 'formatBlock',
                                name: this.$tc('sw-text-editor-toolbar.title.blockquote'),
                                value: 'blockquote',
                                tag: 'blockquote',
                            },
                        ],
                    },
                    {
                        type: 'foreColor',
                        title: this.$tc('sw-text-editor-toolbar.title.text-color'),
                        value: '',
                        tag: 'font',
                    },
                    {
                        type: 'bold',
                        title: this.$tc('sw-text-editor-toolbar.title.bold'),
                        icon: 'default-text-editor-bold',
                        tag: 'b',
                    },
                    {
                        type: 'italic',
                        title: this.$tc('sw-text-editor-toolbar.title.italic'),
                        icon: 'default-text-editor-italic',
                        tag: 'i',
                    },
                    {
                        type: 'underline',
                        title: this.$tc('sw-text-editor-toolbar.title.underline'),
                        icon: 'default-text-editor-underline',
                        tag: 'u',
                    },
                    {
                        type: 'strikethrough',
                        title: this.$tc('sw-text-editor-toolbar.title.strikethrough'),
                        icon: 'default-text-editor-strikethrough',
                        tag: 'strike',
                    },
                    {
                        type: 'superscript',
                        title: this.$tc('sw-text-editor-toolbar.title.superscript'),
                        icon: 'default-text-editor-superscript',
                        tag: 'sup',
                    },
                    {
                        type: 'subscript',
                        title: this.$tc('sw-text-editor-toolbar.title.subscript'),
                        icon: 'default-text-editor-subscript',
                        tag: 'sub',
                    },
                    {
                        type: 'justify',
                        title: this.$tc('sw-text-editor-toolbar.title.textAlign'),
                        icon: 'default-text-editor-align-left',
                        children: [
                            {
                                type: 'justifyLeft',
                                title: this.$tc('sw-text-editor-toolbar.title.alignLeft'),
                                icon: 'default-text-align-left',
                            },
                            {
                                type: 'justifyCenter',
                                title: this.$tc('sw-text-editor-toolbar.title.alignCenter'),
                                icon: 'default-text-align-center',
                            },
                            {
                                type: 'justifyRight',
                                title: this.$tc('sw-text-editor-toolbar.title.alignRight'),
                                icon: 'default-text-align-right',
                            },
                            {
                                type: 'justifyFull',
                                title: this.$tc('sw-text-editor-toolbar.title.justify'),
                                icon: 'default-text-align-justify',
                            },
                        ],
                    },
                    {
                        type: 'insertUnorderedList',
                        title: this.$tc('sw-text-editor-toolbar.title.insert-unordered-list'),
                        icon: 'default-text-editor-list-unordered',
                        tag: 'ul',
                    },
                    {
                        type: 'insertOrderedList',
                        title: this.$tc('sw-text-editor-toolbar.title.insert-ordered-list'),
                        icon: 'default-text-editor-list-numberd',
                        tag: 'ol',
                    },
                    {
                        type: 'link',
                        title: this.$tc('sw-text-editor-toolbar.title.link'),
                        icon: 'default-text-editor-link',
                        expanded: false,
                        newTab: false,
                        displayAsButton: false,
                        buttonVariant: '',
                        buttonVariantList: [
                            {
                                id: 'primary',
                                name: this.$tc('sw-text-editor-toolbar.link.buttonVariantPrimary'),
                            },
                            {
                                id: 'secondary',
                                name: this.$tc('sw-text-editor-toolbar.link.buttonVariantSecondary'),
                            },
                            {
                                id: 'primary-sm',
                                name: this.$tc('sw-text-editor-toolbar.link.buttonVariantPrimarySmall'),
                            },
                            {
                                id: 'secondary-sm',
                                name: this.$tc('sw-text-editor-toolbar.link.buttonVariantSecondarySmall'),
                            },
                        ],
                        value: '',
                        tag: 'a',
                    },
                    {
                        type: 'undo',
                        title: this.$tc('sw-text-editor-toolbar.title.undo'),
                        icon: 'default-text-editor-undo',
                        position: 'middle',
                    },
                    {
                        type: 'redo',
                        title: this.$tc('sw-text-editor-toolbar.title.redo'),
                        icon: 'default-text-editor-redo',
                        position: 'middle',
                    },
                ];
            },
        },
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
            isCodeEdit: false,
            tableData: {
                pageX: null,
                curCol: null,
                nextCol: null,
                curColWidth: null,
                nextColWidth: null,
            },
            isTableEdit: false,
            cmsPageState: Shopware.State.get('cmsPageState'),
        };
    },

    computed: {
        classes() {
            return {
                'is--active': this.isActive,
                'is--disabled': this.disabled,
                'is--boxed': !this.isInlineEdit,
                'is--empty': this.isEmpty,
                'has--vertical-align': !!this.verticalAlign,
            };
        },

        verticalAlignStyle() {
            if (!this.verticalAlign) {
                return null;
            }

            return {
                'justify-content': this.verticalAlign,
            };
        },

        availableDataMappings() {
            let mappings = [];

            Object.entries(this.cmsPageState.currentMappingTypes).forEach(entry => {
                const [type, value] = entry;

                if (type === 'string') {
                    mappings = [...mappings, ...value];
                }
            });

            return mappings;
        },
    },

    watch: {
        value: {
            handler() {
                if (this.$refs.textEditor && this.value !== this.$refs.textEditor.innerHTML) {
                    this.$refs.textEditor.innerHTML = '';
                    this.content = this.value;
                    this.isEmpty = this.emptyCheck(this.content);
                    this.placeholderVisible = this.isEmpty;
                }

                this.$nextTick(() => {
                    this.setWordCount();
                    this.setTablesResizable();
                });
            },
        },

        isCodeEdit() {
            if (!this.isCodeEdit) {
                this.$nextTick(() => {
                    this.setWordCount();
                });
            }
        },
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

            if (!this.$options.buttonConfig) {
                this.buttonConfig.push({
                    type: 'table',
                    title: this.$tc('sw-text-editor-toolbar.title.insert-table'),
                    icon: 'default-text-editor-table',
                    tag: 'table',
                    expanded: false,
                    handler: this.handleInsertTable,
                });

                if (!this.isInlineEdit) {
                    this.buttonConfig.push({
                        type: 'codeSwitch',
                        title: this.$tc('sw-text-editor-toolbar.title.code-switch'),
                        icon: 'default-text-editor-code',
                        expanded: this.isCodeEdit,
                        handler: this.toggleCodeEditor,
                        position: 'right',
                    });
                }

                if (
                    this.allowInlineDataMapping &&
                    this.availableDataMappings.length > 0
                ) {
                    const dataMappingButton = {
                        type: 'data-mapping',
                        title: this.$tc('sw-text-editor-toolbar.title.data-mapping'),
                        icon: 'default-text-editor-variables',
                        position: 'left',
                        dropdownPosition: 'left',
                        tooltipShowDelay: 500,
                        tooltipHideDelay: 100,
                    };

                    const buttonConfigs = this.availableDataMappings.map(mapping => (
                        {
                            type: mapping,
                            name: mapping,
                            title: mapping,
                            handler: this.handleInsertDataMapping,
                        }
                    ));

                    dataMappingButton.children = buttonConfigs;

                    this.buttonConfig.push(dataMappingButton);
                }
            }

            document.addEventListener('mouseup', this.onSelectionChange);
            document.addEventListener('mousedown', this.onSelectionChange);
            document.addEventListener('keydown', this.onSelectionChange);
        },

        toggleCodeEditor(buttonConf) {
            this.isCodeEdit = !this.isCodeEdit;
            buttonConf.expanded = !buttonConf.expanded;
        },

        handleInsertDataMapping({ name }) {
            this.onTextStyleChange('insertText', `{{ ${name} }}`);

            this.selection = document.getSelection();
        },

        mountedComponent() {
            this.isEmpty = this.emptyCheck(this.content);
            this.placeholderVisible = this.isEmpty;

            this.$nextTick(() => {
                this.setWordCount();
                this.setTablesResizable();
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

            if (path.includes(this.toolbar) && event?.target?.tagName !== 'INPUT') {
                event.preventDefault();
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
                },
            );
        },

        onToolbarCreated(elem) {
            this.toolbar = elem;
        },

        onToolbarDestroyed() {
            this.toolbar = null;
        },

        onTextStyleChange(type, value) {
            const selectedText = document.getSelection().toString();

            if (selectedText.length > 0) {
                const selectionContainsStartBracket = this.containsStartBracket(selectedText);
                const selectionContainsEndBracket = this.containsEndBracket(selectedText);
                const isInsideInlineMapping = this.isInsideInlineMapping();

                if (selectionContainsStartBracket && !selectionContainsEndBracket) {
                    this.expandSelectionToNearestEndBracket();
                }

                if (!selectionContainsStartBracket && selectionContainsEndBracket) {
                    this.expandSelectionToNearestStartBracket();
                }

                if (isInsideInlineMapping) {
                    this.expandSelectionToNearestStartBracket();
                    this.expandSelectionToNearestEndBracket();
                }
            }

            document.execCommand(type, false, value);
            this.emitContent();
        },

        expandSelectionToNearestEndBracket() {
            const {
                anchorNode,
                anchorOffset,
                focusNode,
                focusNode: { nodeValue: focusNodeText },
                focusOffset,
            } = this.selection;

            const contentAfterSelection = Array.from(focusNodeText)
                .splice(focusOffset, focusNodeText.length)
                .join('');
            const positionOfEndBracket = contentAfterSelection.indexOf('}}');
            const containsBothStartBrackets = /\{\{/.test(this.selection.toString());

            this.setSelection(
                anchorNode,
                focusNode,
                containsBothStartBrackets ? anchorOffset : anchorOffset - 1,
                focusOffset + positionOfEndBracket + 2,
            );
        },

        expandSelectionToNearestStartBracket() {
            const {
                anchorOffset,
                anchorNode,
                anchorNode: { nodeValue: anchorNodeText },
                focusNode,
                focusOffset,
            } = this.selection;

            const contentBeforeSelection = Array.from(anchorNodeText)
                .splice(0, anchorOffset)
                .reverse()
                .join('');
            const positionOfStartBracket = contentBeforeSelection.indexOf('{{');
            const containsBothEndBrackets = /}}/.test(this.selection.toString());

            this.setSelection(
                anchorNode,
                focusNode,
                anchorOffset - positionOfStartBracket - 2,
                containsBothEndBrackets ? focusOffset : focusOffset + 1,
            );
        },

        setSelection(anchorNode, focusNode, start, end) {
            const range = new Range();
            range.setStart(anchorNode, start);
            range.setEnd(focusNode, end);

            this.selection.empty();
            this.selection.addRange(range);
        },

        containsStartBracket(selection) {
            const regex = /\{{1,2}/;

            return regex.test(selection);
        },

        containsEndBracket(selection) {
            const regex = /}{1,2}/;

            return regex.test(selection);
        },

        isInsideInlineMapping() {
            /* go to the right and check if there is a '}'. And if there's one it should be before and '{'
             * go to the left and do the same just swap the chars.
             */
            const selectedText = this.selection.toString();
            const containsStartBracket = selectedText.includes('{');
            const containsEndBracket = selectedText.includes('}');

            if (containsStartBracket || containsEndBracket) {
                return false;
            }

            const {
                anchorOffset,
                anchorNode: { nodeValue: anchorNodeText },
                focusOffset,
                focusNode: { nodeValue: focusNodeText },
            } = this.selection;

            const contentBeforeSelection = Array.from(anchorNodeText)
                .splice(0, anchorOffset)
                .reverse()
                .join('');
            // https://regex101.com/r/HWsZiH/1
            const startBracketFound = (/^[^}]*{{/).test(contentBeforeSelection);

            const contentAfterSelection = Array.from(focusNodeText)
                .splice(focusOffset, focusNodeText.length)
                .join('');
            // https://regex101.com/r/nzzL4t/1
            const endBracketFound = (/^[^{]*}}/).test(contentAfterSelection);

            return !!startBracketFound && !!endBracketFound;
        },

        handleInsertTable(button) {
            this.onTextStyleChange('insertHTML', button.value);
            this.selection = document.getSelection();

            this.$nextTick(() => {
                this.setTablesResizable();
                this.isTableEdit = true;
            });
        },

        setTablesResizable() {
            const tables = this.$el.querySelectorAll('.sw-text-editor-table');

            if (tables) {
                tables.forEach((table) => {
                    this.setTableResizable(table);
                });
            }
        },

        setTableResizable(table) {
            const row = table.getElementsByTagName('tr')[0];
            const cols = row ? row.children : undefined;

            if (!cols) {
                return;
            }

            const resizeSelectors = table.querySelectorAll('.sw-text-editor-table__col-selector');

            if (resizeSelectors.length > 0) {
                resizeSelectors.forEach((selector) => {
                    selector.style.height = `${table.offsetHeight}px`;
                    selector.contentEditable = false;
                    this.setTableSelectorListeners(selector);
                });

                this.setTableListeners();
            }
        },

        setTableSelectorListeners(selector) {
            selector.addEventListener('mousedown', (e) => {
                this.tableData.curCol = e.target.parentElement;
                this.tableData.nextCol = this.tableData.curCol.nextElementSibling;
                this.tableData.pageX = e.pageX;
                this.tableData.curColWidth = this.tableData.curCol.offsetWidth;
                if (this.tableData.nextCol) {
                    this.tableData.nextColWidth = this.tableData.nextCol.offsetWidth;
                }
            });
        },

        setTableListeners() {
            this.$el.addEventListener('mousemove', (e) => {
                if (this.tableData.curCol) {
                    const diffX = e.pageX - this.tableData.pageX;

                    if (this.tableData.nextCol) {
                        this.tableData.nextCol.style.width = `${this.tableData.nextColWidth - (diffX)}px`;
                    }

                    this.tableData.curCol.style.width = `${this.tableData.curColWidth + diffX}px`;
                }
            });

            this.$el.addEventListener('mouseup', () => {
                this.tableData.curCol = null;
                this.tableData.nextCol = null;
                this.tableData.pageX = null;
                this.tableData.nextColWidth = null;
                this.tableData.curColWidth = null;
            });
        },

        onSetLink(value, target, buttonVariant) {
            if (!this.selection.toString()) {
                return;
            }

            const classes = [];
            const attributes = [
                `target="${target}"`,
                `href="${value}"`,
            ];

            if (target === '_blank') {
                attributes.push('rel="noopener"');
            }

            if (buttonVariant) {
                classes.push('btn');
                classes.push(...buttonVariant.split('-').map(cls => `btn-${cls}`));
            }

            if (classes.length > 0) {
                attributes.push(`class="${classes.join(' ')}"`);
            }

            this.onTextStyleChange('insertHTML', `<a ${attributes.join(' ')}>${this.selection}</a>`);
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
        },

        onTableEdit(toggle) {
            this.isTableEdit = toggle;
        },

        onTableModify(table) {
            this.$nextTick(() => {
                this.setTableResizable(table);
            });
        },

        onTableDelete(event) {
            event.stopPropagation();
            this.isTableEdit = false;
        },
    },
});
