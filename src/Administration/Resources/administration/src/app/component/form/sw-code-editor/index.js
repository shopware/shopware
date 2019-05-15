import Ace from 'ace-builds/src-noconflict/ace';
import {
    setCompleters,
    textCompleter
} from 'ace-builds/src-noconflict/ext-language_tools';
import utils from 'src/core/service/util.service';
import 'ace-builds/src-noconflict/mode-twig';
import template from './sw-code-editor.html.twig';
import './sw-code-editor.scss';

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
export default {
    name: 'sw-code-editor',
    template,

    props: {
        value: {
            type: String,
            required: false,
            default: ''
        },

        label: {
            type: String,
            required: false,
            default: ''
        },

        completerFunction: {
            type: Function,
            required: false,
            default: null
        },

        editorConfig: {
            type: Object,
            required: false,
            default: {}
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
            }
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
            }
        },

        softWraps: {
            type: Boolean,
            required: false,
            default: true
        },

        // set focus to the component when initially mounted
        setFocus: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    data() {
        return {
            editor: {},
            editorId: utils.createId()
        };
    },

    watch: {
        value(value) {
            if (value !== this.editor.getValue()) {
                if (value !== null) {
                    this.editor.setValue(value);
                } else {
                    this.editor.setValue('');
                }
            }
        },

        softWraps() {
            this.editor.session.setOption('wrap', this.softWraps);
        }
    },

    mounted() {
        this.mountedComponent();
    },

    destroyed() {
        this.destroyedComponent();
    },

    methods: {
        mountedComponent() {
            const config = {
                ...{ mode: `ace/mode/${this.mode}`,
                    showPrintMargin: false,
                    wrap: this.softWraps },
                ...this.editorConfig
            };
            this.editor = Ace.edit(this.$refs['editor'.concat(this.editorId)], config);

            this.defineAutocompletion();

            this.editor.setValue(this.value, 1);
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

        onChange() {
            const value = this.editor.getValue();
            this.$emit('change', value);
        },

        onBlur() {
            const value = this.editor.getValue();
            this.$emit('blur', value);
        },
        defineAutocompletion() {
            /**
             * Sets a completer function. If completitionMode is set to "entity"
             * Autocomplete can handle [x] and . otherwise it uses the default
             * RegEx from ACE.
             * The ExecCommands sets a hook to the "insertstring" command to
             * prevent the autocompletion-popup to hide after a .
             */
            setCompleters([]);
            if (this.completerFunction) {
                const textCompleterClonedPlain = JSON.parse(JSON.stringify(textCompleter));
                const textCompleterCloned = JSON.parse(JSON.stringify(textCompleter));
                if (this.completionMode === 'entity') {
                    textCompleterCloned.identifierRegexps = [/[\[\]\.a-zA-Z_0-9\$\-\u00A2-\uFFFF]/];
                    textCompleterCloned.getCompletions = function getComps(editor, session, pos, prefix, callback) {
                        this.identifierRegexps = [/[\[\][a-zA-Z_0-9\$\-\u00A2-\uFFFF]/];
                        callback(null, this.completerFunction(prefix));
                        this.identifierRegexps = [/[\[\]\.a-zA-Z_0-9\$\-\u00A2-\uFFFF]/];
                    };
                    textCompleterCloned.completerFunction = this.completerFunction;
                    this.editor.completers = [textCompleterCloned];
                    const startCallback = (function startCall(e) {
                        if (e.command.name === 'insertstring') {
                            e.editor.execCommand('startAutocomplete', null);
                        }
                    });
                    this.editor.commands.on('afterExec', startCallback);
                } else {
                    textCompleterClonedPlain.identifierRegexps = null;
                    textCompleterClonedPlain.getCompletions = function getComps(editor, session, pos, prefix, callback) {
                        callback(null, this.completerFunction(prefix));
                    };
                    textCompleterClonedPlain.completerFunction = this.completerFunction;
                    this.editor.completers = [textCompleterClonedPlain];
                }
            } else {
                this.editor.completers = [];
            }
        }
    }
};
