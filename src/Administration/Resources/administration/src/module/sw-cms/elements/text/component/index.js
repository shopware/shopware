import { Component, Mixin } from 'src/core/shopware';
import template from './sw-cms-el-text.html.twig';
import './sw-cms-el-text.scss';

Component.register('sw-cms-el-text', {
    template,

    mixins: [
        Mixin.getByName('cms-element')
    ],

    data() {
        return {
            editable: true,
            hasFocus: false
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('text');
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
            this.element.config.content.value = this.getContent();
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
