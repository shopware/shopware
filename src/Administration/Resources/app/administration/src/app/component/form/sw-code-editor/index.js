import Ace from 'ace-builds/src-noconflict/ace';
import { setCompleters, textCompleter } from 'ace-builds/src-noconflict/ext-language_tools';
import 'ace-builds/src-noconflict/mode-twig';
import template from './sw-code-editor.html.twig';
import './sw-code-editor.scss';

const { Component } = Shopware;
const utils = Shopware.Utils;

/**
 * @public
 * @status ready
 * @description
 * Renders a code editor
 * @example-type dynamic
 * @component-example
 * <sw-code-editor label="Description">
 * </sw-code-editor>
 */
Component.register('sw-code-editor', {
    template,

    inject: [
        'feature',
        'userInputSanitizeService',
    ],

    props: {
        value: {
            type: String,
            required: false,
            default: '',
        },

        label: {
            type: String,
            required: false,
            default: '',
        },

        completerFunction: {
            type: Function,
            required: false,
            default: null,
        },

        editorConfig: {
            type: Object,
            required: false,
            default() {
                return {};
            },
        },

        completionMode: {
            type: String,
            required: false,
            default: 'text',
            validValues: ['entity', 'text'],
            validator(value) {
                if (!value.length) {
                    return true;
                }
                return ['entity', 'text'].includes(value);
            },
        },

        mode: {
            type: String,
            required: false,
            default: 'twig',
            validValues: ['twig', 'text'],
            validator(value) {
                if (!value.length) {
                    return true;
                }
                return ['twig', 'text'].includes(value);
            },
        },

        softWraps: {
            type: Boolean,
            required: false,
            default: true,
        },

        // set focus to the component when initially mounted
        setFocus: {
            type: Boolean,
            required: false,
            default: false,
        },

        disabled: {
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
    },

    data() {
        return {
            editor: {},
            editorId: utils.createId(),
            contentWasSanitized: false,
        };
    },

    computed: {
        aceConfig() {
            return {
                ...{
                    mode: `ace/mode/${this.mode}`,
                    showPrintMargin: false,
                    wrap: this.softWraps,
                    readOnly: this.disabled,
                },
                ...this.editorConfig,
            };
        },
    },

    watch: {
        value(value) {
            if (value !== null && value !== this.editor.getValue()) {
                this.editor.setValue(value, 1);
            }
        },

        softWraps() {
            this.editor.session.setOption('wrap', this.softWraps);
        },
    },

    mounted() {
        this.mountedComponent();
    },

    destroyed() {
        this.destroyedComponent();
    },

    methods: {
        mountedComponent() {
            this.editor = Ace.edit(this.$refs['editor'.concat(this.editorId)], this.aceConfig);

            this.defineAutocompletion(this.completerFunction);

            this.editor.setValue(this.value || '', 1);
            this.editor.on('input', this.onInput);
            this.editor.on('blur', this.onBlur);

            if (this.setFocus) {
                this.editor.focus();
            }
        },

        destroyedComponent() {
            delete this.editor;
        },

        onInput() {
            const value = this.editor.getValue();

            if (this.value !== value) {
                this.$emit('input', value);
            }
        },

        async onBlur() {
            const value = await this.sanitizeEditorInput(this.editor.getValue());

            this.$emit('blur', value);
        },

        async sanitizeEditorInput(value) {
            if (
                this.feature.isActive('FEATURE_NEXT_15172') &&
                this.sanitizeInput
            ) {
                this.contentWasSanitized = false;

                if (this.value !== value) {
                    try {
                        const sanitizedValue = await this.userInputSanitizeService.sanitizeInput({
                            html: value,
                            field: this.sanitizeFieldName,
                        });

                        this.contentWasSanitized = sanitizedValue?.preview !== value;

                        if (this.contentWasSanitized) {
                            this.editor.setValue(sanitizedValue?.preview ?? value, 1);
                            return this.editor.getValue();
                        }
                    } catch (ignore) { /* api endpoint did not work, keep user entry */ }
                }
            }
            return value;
        },

        defineAutocompletion(completerFunction) {
            /**
             * Sets a completer function. If completitionMode is set to "entity"
             * Autocomplete can handle [x] and . otherwise it uses the default
             * RegEx from ACE.
             * The ExecCommands sets a hook to the "insertstring" command to
             * prevent the autocompletion-popup to hide after a .
             */
            setCompleters([]);
            if (completerFunction) {
                const textCompleterClonedPlain = JSON.parse(JSON.stringify(textCompleter));
                const textCompleterCloned = JSON.parse(JSON.stringify(textCompleter));

                if (this.completionMode === 'entity') {
                    textCompleterCloned.identifierRegexps = [/[\[\]\.a-zA-Z_0-9\$\-\u00A2-\uFFFF]/];

                    textCompleterCloned.getCompletions = function getComps(editor, session, pos, prefix, callback) {
                        this.identifierRegexps = [/[\[\][a-zA-Z_0-9\$\-\u00A2-\uFFFF]/];
                        callback(null, completerFunction(prefix));
                        this.identifierRegexps = [/[\[\]\.a-zA-Z_0-9\$\-\u00A2-\uFFFF]/];
                    };

                    textCompleterCloned.completerFunction = completerFunction;
                    this.editor.completers = [textCompleterCloned];

                    const startCallback = (function startCall(e) {
                        if (e.command.name === 'insertstring') {
                            if (e.args !== '\n' && e.args !== ' ') {
                                e.editor.execCommand('startAutocomplete', null);
                            }
                        }
                    });

                    this.editor.commands.on('afterExec', startCallback);
                } else {
                    textCompleterClonedPlain.identifierRegexps = null;
                    textCompleterClonedPlain.getCompletions = function getComps(editor, session, pos, prefix, callback) {
                        callback(null, completerFunction(prefix));
                    };

                    textCompleterClonedPlain.completerFunction = completerFunction;
                    this.editor.completers = [textCompleterClonedPlain];
                }
            } else {
                this.editor.completers = [];
            }
        },
    },
});
