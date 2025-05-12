import type { PropType } from 'vue';
import template from './sw-cms-el-location-renderer.html.twig';
import './sw-cms-el-location-renderer.scss';
import type { ElementDataProp } from '../index';

const { Component, Mixin } = Shopware;

/**
 * @private
 * @sw-package discovery
 */
export default Component.wrapComponentConfig({
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
            // Add this.element.id to the url as a query param
            const url = new URL(this.elementData.appData.baseUrl);
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

            /**
             * @deprecated tag:v6.8.0 - Will be removed
             */
            this.unpublishData = Shopware.ExtensionAPI.publishData({
                id: this.publishingKey,
                path: 'element',
                scope: this,
                deprecated: true,
                deprecationMessage:
                    // eslint-disable-next-line max-len
                    'The general cms element data set is deprecated. Please use a specific cms data set instead by provoding the element id.',
            });

            this.unpublishDataWithElementId = Shopware.ExtensionAPI.publishData({
                // eslint-disable-next-line @typescript-eslint/restrict-template-expressions
                id: `${this.publishingKey}__${this.element.id}`,
                path: 'element',
                scope: this,
            });
        },
    },
});
