import type { PropType } from 'vue';
import template from './sw-cms-el-config-location-renderer.html.twig';
import type { ElementDataProp } from '../index';

const { Component, Mixin } = Shopware;

/**
 * @private
 * @package content
 */
Component.register('sw-cms-el-config-location-renderer', {
    template,

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    props: {
        elementData: {
            type: Object as PropType<ElementDataProp>,
            required: true,
        },
    },

    computed: {
        src(): string {
            return this.elementData.appData.baseUrl;
        },

        configLocation(): string {
            return `${this.elementData.name}-config`;
        },

        publishingKey(): string {
            return `${this.elementData.name}__config-element`;
        },
    },

    watch: {
        element() {
            this.$emit('element-update', this.element);
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig(this.elementData.name);

            Shopware.ExtensionAPI.publishData({
                id: this.publishingKey,
                path: 'element',
                scope: this,
            });
        },

        onBlur(content: unknown) {
            this.emitChanges(content);
        },

        onInput(content: unknown) {
            this.emitChanges(content);
        },

        emitChanges(content: unknown) {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
            if (content !== this.element.config.content.value) {
                // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
                this.element.config.content.value = content;

                this.$emit('element-update', this.element);
            }
        },
    },
});
