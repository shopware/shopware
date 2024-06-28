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
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        Mixin.getByName('cms-element') as any,
    ],

    props: {
        elementData: {
            type: Object as PropType<ElementDataProp>,
            required: true,
        },
    },

    computed: {
        src(): string {
            // Add this.element.id to the url as a query param
            const url = new URL(this.elementData.appData.baseUrl);
            // @ts-expect-error - is defined in mixin
            // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
            url.searchParams.set('elementId', this.element.id);

            return url.toString();
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

        elementData: {
            handler(): void {
                this.updatePublishData();
            },
            deep: true,
        },
    },

    created(): void {
        this.createdComponent();
    },

    data(): {
        unpublishData: null | (() => void);
        unpublishDataWithElementId: null | (() => void);
        } {
        return {
            unpublishData: null,
            unpublishDataWithElementId: null,
        };
    },

    methods: {
        createdComponent(): void {
            // @ts-expect-error - is defined in mixin
            this.initElementConfig(this.elementData.name);
            this.updatePublishData();
        },

        updatePublishData() {
            if (this.unpublishData) {
                this.unpublishData();
            }

            if (this.unpublishDataWithElementId) {
                this.unpublishDataWithElementId();
            }

            // This is just for avoiding breaking changes for older implementations.
            // The important part is the publisher with the element id.
            this.unpublishData = Shopware.ExtensionAPI.publishData({
                id: this.publishingKey,
                path: 'element',
                scope: this,
            });

            this.unpublishDataWithElementId = Shopware.ExtensionAPI.publishData({
                // @ts-expect-error - is defined in mixin
                // eslint-disable-next-line @typescript-eslint/restrict-template-expressions
                id: `${this.publishingKey}__${this.element.id}`,
                path: 'element',
                scope: this,
            });
        },
    },
});
