import template from './sw-cms-el-location-renderer.html.twig';
import './sw-cms-el-location-renderer.scss';
import { cmsElementMixin, type ElementDataProp, publishData, wrapComponentConfig } from '../dependencies';

/**
 * @private
 * @sw-package discovery
 */
export default wrapComponentConfig({
    template,

    emits: ['element-update'],

    mixins: [cmsElementMixin],

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
            this.initElementConfig();
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
            this.unpublishData = publishData({
                id: this.publishingKey,
                path: 'element',
                scope: this,
                deprecated: true,
                deprecationMessage:
                    'The general cms element data set is deprecated. Please use a specific cms data set instead by provoding the element id.',
            });

            this.unpublishDataWithElementId = publishData({
                id: `${this.publishingKey}__${this.element.id}`,
                path: 'element',
                scope: this,
            });
        },
    },
});
