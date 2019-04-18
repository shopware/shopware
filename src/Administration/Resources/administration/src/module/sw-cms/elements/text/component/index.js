import { Component, Mixin, State } from 'src/core/shopware';
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
            demoValue: ''
        };
    },

    computed: {
        cmsPageState() {
            return State.getStore('cmsPageState');
        }
    },

    watch: {
        'cmsPageState.currentDemoEntity': {
            handler() {
                if (this.element.config.content.source === 'mapped') {
                    this.demoValue = this.getDemoValue(this.element.config.content.value) || '';
                }
            }
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('text');

            if (this.element.config.content.source === 'mapped') {
                this.demoValue = this.getDemoValue(this.element.config.content.value) || '';
            }
        },

        onBlur(content) {
            this.emitChanges(content);
        },

        onInput(content) {
            this.emitChanges(content);
        },

        emitChanges(content) {
            if (content !== this.element.config.content.value) {
                this.element.config.content.value = content;
                this.$emit('element-update', this.element);
            }
        }
    }
});
