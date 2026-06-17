import type { ContentElementNode } from '../../types/content-element.types';
import type {
    ContentSystemElementTypeProperty,
    ContentSystemElementTypeSpecification,
} from 'src/core/service/api/content-system-element-type.api.service';
import {
    getAdminUiProps as getPropertyAdminUiProps,
    getInitialPropertyValue,
    getPropertyControlType,
} from '../../util/element-settings.util';
import template from './sw-experience-studio-element-settings.html.twig';
import './sw-experience-studio-element-settings.scss';

type PrimitiveValue = string | number | boolean | null;

/**
 * @private
 * @sw-package discovery
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        layout: {
            type: Object,
            required: false,
            default: null,
        },
        selectedElementId: {
            type: String,
            required: false,
            default: null,
        },
        selectedElement: {
            type: Object,
            required: false,
            default: null,
        },
        selectedElementType: {
            type: Object,
            required: false,
            default: null,
        },
        isLoadingTypes: {
            type: Boolean,
            required: false,
            default: false,
        },
        typeLoadError: {
            type: String,
            required: false,
            default: null,
        },
        allowEdit: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    emits: [
        'update-properties',
    ],

    computed: {
        hasSelectedElement(): boolean {
            return this.selectedElementId !== null;
        },

        hasTypeLoadError(): boolean {
            return typeof this.typeLoadError === 'string' && this.typeLoadError.length > 0;
        },

        hasSelectedElementType(): boolean {
            return this.selectedElementType !== null;
        },

        editableProperties(): Array<{ key: string; property: ContentSystemElementTypeProperty }> {
            const typeSpecification = this.selectedElementType as ContentSystemElementTypeSpecification | null;

            if (!typeSpecification) {
                return [];
            }

            return Object.entries(typeSpecification.properties)
                .filter(([, property]) => getPropertyControlType(property) !== null)
                .map(([key, property]) => ({ key, property }));
        },
    },

    methods: {
        getControlType(property: ContentSystemElementTypeProperty): string | null {
            return getPropertyControlType(property);
        },

        getPropertyValue(key: string, property: ContentSystemElementTypeProperty): PrimitiveValue {
            const selectedElement = this.selectedElement as ContentElementNode | null;
            const currentValue = selectedElement?.properties?.[key];

            return getInitialPropertyValue(property, currentValue);
        },

        getSelectOptions(property: ContentSystemElementTypeProperty): Array<{ value: PrimitiveValue; label: string }> {
            if (!Array.isArray(property.enum)) {
                return [];
            }

            return property.enum.map((value) => ({
                value,
                label: String(value),
            }));
        },

        getEntityName(property: ContentSystemElementTypeProperty): string | null {
            const entity = property.adminUI?.entity;

            return typeof entity === 'string' && entity.length > 0 ? entity : null;
        },

        getControlProps(property: ContentSystemElementTypeProperty): Record<string, unknown> {
            return getPropertyAdminUiProps(property);
        },

        onUpdateProperty(key: string, value: PrimitiveValue): void {
            const selectedElement = this.selectedElement as ContentElementNode | null;

            if (!selectedElement || !this.allowEdit) {
                return;
            }

            this.$emit('update-properties', {
                elementId: selectedElement.id,
                properties: {
                    [key]: value,
                },
            });
        },
    },
});
