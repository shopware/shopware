import { Component } from 'src/core/shopware';
import template from './sw-cms-el-text.html.twig';
import './sw-cms-el-text.scss';

Component.register('sw-cms-el-text', {
    template,

    model: {
        prop: 'element',
        event: 'element-update'
    },

    props: {
        element: {
            type: Object,
            required: true,
            default() {
                return {};
            }
        }
    },

    data() {
        return {
            editable: true,
            hasFocus: false
        };
    },

    computed: {
        defaultContent() {
            return `
                <h2>Lorem Ipsum dolor sit amet</h2>
                <p>Lorem ipsum dolor sit amet, consetetur sadipscing elitr, 
                sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, 
                sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. 
                Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. 
                Lorem ipsum dolor sit amet, consetetur sadipscing elitr, 
                sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. 
                At vero eos et accusam et justo duo dolores et ea rebum. 
                Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.</p>
            `;
        }
    },

    created() {
        this.componentCreated();
    },

    methods: {
        componentCreated() {
            if (!this.element.config.content || !this.element.config.content.length) {
                this.element.config.content = this.defaultContent;
            }
        },

        getContent() {
            return this.$refs.contentEditor.innerHTML;
        },

        onClick() {
            this.hasFocus = true;
        },

        onFocus() {
            this.setFocus();
            document.execCommand('defaultParagraphSeparator', false, 'p');
        },

        onBlur() {
            this.element.config.content = this.getContent();
            this.$emit('element-update', this.element);
        },

        setFocus() {
            if (!this.hasFocus) {
                document.addEventListener('click', this.onDocumentClick);
                this.hasFocus = true;
            }
        },

        removeFocus() {
            if (this.hasFocus) {
                this.hasFocus = false;
                document.removeEventListener('click', this.onDocumentClick);
            }
        },

        onDocumentClick(event) {
            if (!event.path.includes(this.$el)) {
                this.removeFocus();
            }
        },

        onSetBold() {
            this.hasFocus = true;
            document.execCommand('bold', false, true);
        },

        onSetItalic() {
            document.execCommand('italic', false, true);
        },

        onSetUnderline() {
            document.execCommand('underline', false, true);
        },

        onSetJustifyLeft() {
            document.execCommand('justifyLeft', false, true);
        },

        onSetJustifyRight() {
            document.execCommand('justifyRight', false, true);
        },

        onSetJustifyCenter() {
            document.execCommand('justifyCenter', false, true);
        },

        onSetJustifyFull() {
            document.execCommand('justifyFull', false, true);
        }
    }
});
