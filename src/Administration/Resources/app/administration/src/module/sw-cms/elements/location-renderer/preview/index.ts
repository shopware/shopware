import type { PropType } from 'vue';
import template from './sw-cms-el-preview-location-renderer.html.twig';
import type { ElementDataProp } from '../index';

const { Component } = Shopware;

/**
 * @private
 * @package content
 */
Component.register('sw-cms-el-preview-location-renderer', {
    template,

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

        previewLocation(): string {
            return `${this.elementData.name}-preview`;
        },

        publishingKey(): string {
            return `${this.elementData.name}__config-element`;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            // @ts-expect-error
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
            this.initElementConfig(this.elementData.name);

            Shopware.ExtensionAPI.publishData({
                id: this.publishingKey,
                path: 'element',
                scope: this,
            });
        },
    },
});
