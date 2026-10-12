import template from './sw-mixin-cms-element-scaffold.html.twig';

/**
 * @sw-package framework
 */
export default {
    template,

    emits: ['element-update'],

    mixins: [
        Shopware.Mixin.getByName('cms-element'),
    ],

    data() {
        return {
            demoValue: '',
        };
    },

    computed: {
        contentValue() {
            return this.element.config.content.value;
        },
    },

    watch: {
        'cmsPageState.currentDemoEntity': {
            handler() {
                this.updateDemoValue();
            },
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('text');
            this.initElementData('text');
            this.updateDemoValue();
        },

        updateDemoValue() {
            if (this.element.config.content.source === 'mapped') {
                this.demoValue = this.getDemoValue(this.element.config.content.value);
            }
        },

        // The element is the slot the cmsPage store owns, and the editor writes to it in place.
        onInput(content) {
            this.element.config.content.value = content;
            this.$emit('element-update', this.element);
        },
    },
};
