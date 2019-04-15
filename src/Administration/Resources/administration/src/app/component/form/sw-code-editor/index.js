import Ace from 'ace-builds/src-noconflict/ace';
import 'ace-builds/src-noconflict/mode-twig';
import { Mixin } from 'src/core/shopware';
import template from './sw-code-editor.html.twig';
import './sw-code-editor.scss';

/**
 * @public
 * @status ready
 * @description
 * Renders a code ed
 * @example-type dynamic
 * @component-example
 * <sw-code-editor label="Description">
 * </sw-code-editor>
 */
export default {
    name: 'sw-code-editor',
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
        label: {
            required: false,
            default: ''
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
        }
    },

    data() {
        return {
            editor: {}
        };
    },

    watch: {
        value(value) {
            if (value !== this.editor.getValue()) {
                this.editor.setValue(value);
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
            this.editor = Ace.edit(this.$refs.editor, {
                mode: `ace/mode/${this.mode}`,
                showPrintMargin: false,
                wrap: this.softWraps
            });
            this.editor.setValue(this.value);
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
        }
    }
};
