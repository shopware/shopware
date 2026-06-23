import template from './sw-experience-studio-preview-node.html.twig';
import './sw-experience-studio-preview-node.scss';

type PreviewElement = {
    id: string;
    component: string;
    properties?: Record<string, unknown>;
    slots?: Record<string, PreviewElement[]>;
};

type PreviewPrimitive = string | number | boolean | null;

/**
 * @private
 * @sw-package discovery
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        element: {
            type: Object,
            required: true,
        },
    },

    computed: {
        primitiveProperties(): Array<{ key: string; value: string }> {
            const element = this.element as PreviewElement;
            const properties = element.properties ?? {};

            return Object.entries(properties)
                .filter(([, value]): value is PreviewPrimitive => {
                    return this.isPreviewPrimitive(value);
                })
                .map(([key, value]) => ({
                    key,
                    value: this.formatPrimitiveValue(value),
                }));
        },

        slotEntries(): Array<{ name: string; elements: PreviewElement[] }> {
            const element = this.element as PreviewElement;
            const slots = element.slots ?? {};

            return Object.entries(slots).map(([name, elements]) => ({
                name,
                elements: Array.isArray(elements) ? elements : [],
            }));
        },
    },

    methods: {
        isPreviewPrimitive(value: unknown): value is PreviewPrimitive {
            return (
                value === null
                || typeof value === 'string'
                || typeof value === 'number'
                || typeof value === 'boolean'
            );
        },

        formatPrimitiveValue(value: PreviewPrimitive): string {
            if (value === null) {
                return 'null';
            }

            if (typeof value === 'string') {
                return value;
            }

            if (typeof value === 'number') {
                return value.toString();
            }

            return value ? 'true' : 'false';
        },
    },
});
