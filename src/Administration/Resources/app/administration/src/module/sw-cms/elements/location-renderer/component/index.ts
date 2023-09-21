import type { PropType } from 'vue';
import template from './sw-cms-el-location-renderer.html.twig';
import './sw-cms-el-location-renderer.scss';
import type { ElementDataProp } from '../index';

const { Component, Mixin } = Shopware;

/**
 * @private
 * @package buyers-experience
 */
Component.register('sw-cms-el-location-renderer', {
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

        elementLocation(): string {
            return `${this.elementData.name}-element`;
        },

        publishingKey(): string {
            return `${this.elementData.name}__config-element`;
        },
    },

    watch: {
        element(): void {
            this.$emit('element-update', this.element);
        },
    },

    created(): void {
        this.createdComponent();
    },

    methods: {
        createdComponent(): void {
            this.initElementConfig(this.elementData.name);

            Shopware.ExtensionAPI.publishData({
                id: this.publishingKey,
                path: 'element',
                scope: this,
            });
        },
    },
});
