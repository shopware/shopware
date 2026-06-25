import type { ContentElementNode } from '../../types/content-element.types';
import type {
    ContentSystemElementTypeProperty,
    ContentSystemElementTypeSpecification,
} from 'src/core/service/api/content-system-element-type.api.service';
import {
    getAdminUiProps as getPropertyAdminUiProps,
    getInitialPropertyValue,
    isPropertyVisible,
    getPropertyControlType,
} from '../../util/element-settings.util';
import template from './sw-experience-studio-element-settings.html.twig';
import './sw-experience-studio-element-settings.scss';

type PrimitiveValue = string | number | boolean | null | Record<string, unknown>;
type ResponsiveViewport = 'xs' | 'sm' | 'md' | 'lg' | 'xl';
type ResponsiveValue = Record<ResponsiveViewport, number>;
type RadioPanelOption = {
    value: string;
    label: string;
    icon?: string;
    description?: string;
    disabled?: boolean;
};

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
        isInlineEditingActive: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    emits: [
        'update-properties',
    ],

    data() {
        return {
            expandedResponsiveProperties: {} as Record<string, boolean>,
            responsiveGlobalSnapshots: {} as Record<string, number>,
        };
    },

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
            const selectedElement = this.selectedElement as ContentElementNode | null;

            if (!typeSpecification) {
                return [];
            }

            const resolvedPropertyValues = Object.entries(typeSpecification.properties).reduce<Record<string, PrimitiveValue>>(
                (accumulator, [key, property]) => {
                    const currentValue = selectedElement?.properties?.[key];
                    accumulator[key] = getInitialPropertyValue(property, currentValue);

                    return accumulator;
                },
                {},
            );

            return Object.entries(typeSpecification.properties)
                .filter(([, property]) => getPropertyControlType(property) !== null)
                .filter(([, property]) => isPropertyVisible(property, resolvedPropertyValues))
                .map(([key, property]) => ({ key, property }));
        },
    },

    methods: {
        getControlType(property: ContentSystemElementTypeProperty): string | null {
            return getPropertyControlType(property);
        },

        isInlineTextProperty(key: string, property: ContentSystemElementTypeProperty): boolean {
            const selectedElementType = this.selectedElementType as ContentSystemElementTypeSpecification | null;

            if (!selectedElementType || key !== 'text') {
                return false;
            }

            const matchesTextType = selectedElementType.name.endsWith(':text');
            const matchesTextProperty = Boolean(
                selectedElementType.properties.text
                && this.getControlType(selectedElementType.properties.text) === 'richtext',
            );

            return (matchesTextType || matchesTextProperty) && this.getControlType(property) === 'richtext';
        },

        getPropertyValue(key: string, property: ContentSystemElementTypeProperty): PrimitiveValue {
            const selectedElement = this.selectedElement as ContentElementNode | null;
            const currentValue = selectedElement?.properties?.[key];

            return getInitialPropertyValue(property, currentValue);
        },

        getRawPropertyValue(key: string): unknown {
            const selectedElement = this.selectedElement as ContentElementNode | null;

            return selectedElement?.properties?.[key];
        },

        getResponsiveLimits(property: ContentSystemElementTypeProperty): { min: number; max: number; step: number } {
            const adminProps = this.getControlProps(property);
            const min = this.toNumberOrFallback(adminProps.min, 1);
            const max = this.toNumberOrFallback(adminProps.max, 12);
            const step = this.toNumberOrFallback(adminProps.step, 1);

            return {
                min,
                max,
                step,
            };
        },

        getResponsiveViewports(): ResponsiveViewport[] {
            return ['xs', 'sm', 'md', 'lg', 'xl'];
        },

        getViewportIcon(viewport: ResponsiveViewport): string {
            if (viewport === 'xs' || viewport === 'sm') {
                return 'regular-mobile';
            }

            if (viewport === 'md') {
                return 'regular-tablet';
            }

            return 'regular-desktop';
        },

        isResponsiveViewportMode(key: string): boolean {
            if (this.expandedResponsiveProperties[key]) {
                return true;
            }

            return this.isResponsiveObjectValue(this.getRawPropertyValue(key));
        },

        getResponsiveGlobalValue(key: string, property: ContentSystemElementTypeProperty): number {
            const limits = this.getResponsiveLimits(property);
            const rawValue = this.getRawPropertyValue(key);
            const globalSnapshot = this.responsiveGlobalSnapshots[key];

            if (this.isResponsiveViewportMode(key) && typeof globalSnapshot === 'number') {
                return this.clampResponsiveValue(globalSnapshot, limits.min, limits.max);
            }

            if (typeof rawValue === 'number') {
                return this.clampResponsiveValue(rawValue, limits.min, limits.max);
            }

            if (this.isResponsiveObjectValue(rawValue)) {
                const fallbackOrder: ResponsiveViewport[] = ['md', 'lg', 'sm', 'xs', 'xl'];
                for (const viewport of fallbackOrder) {
                    const candidate = rawValue[viewport];
                    if (typeof candidate === 'number') {
                        return this.clampResponsiveValue(candidate, limits.min, limits.max);
                    }
                }
            }

            const initialValue = getInitialPropertyValue(property, undefined);
            if (typeof initialValue === 'number') {
                return this.clampResponsiveValue(initialValue, limits.min, limits.max);
            }

            return limits.min;
        },

        getResponsiveViewportValue(key: string, viewport: ResponsiveViewport, property: ContentSystemElementTypeProperty): number {
            const limits = this.getResponsiveLimits(property);
            const rawValue = this.getRawPropertyValue(key);

            if (this.isResponsiveObjectValue(rawValue)) {
                const viewportValue = rawValue[viewport];
                if (typeof viewportValue === 'number') {
                    return this.clampResponsiveValue(viewportValue, limits.min, limits.max);
                }
            }

            return this.getResponsiveGlobalValue(key, property);
        },

        onToggleResponsiveViewportMode(key: string, property: ContentSystemElementTypeProperty): void {
            const nextState = !this.isResponsiveViewportMode(key);
            const limits = this.getResponsiveLimits(property);
            this.expandedResponsiveProperties[key] = nextState;

            if (nextState) {
                const globalValue = this.getResponsiveGlobalValue(key, property);
                this.responsiveGlobalSnapshots[key] = globalValue;
                const currentValue = this.getRawPropertyValue(key);

                if (this.isResponsiveObjectValue(currentValue)) {
                    return;
                }

                const responsiveValue = this.getResponsiveViewports().reduce<ResponsiveValue>((accumulator, viewport) => {
                    accumulator[viewport] = this.clampResponsiveValue(globalValue, limits.min, limits.max);

                    return accumulator;
                }, {
                    xs: globalValue,
                    sm: globalValue,
                    md: globalValue,
                    lg: globalValue,
                    xl: globalValue,
                });

                this.onUpdateProperty(key, responsiveValue);

                return;
            }

            const globalValue = this.getResponsiveGlobalValue(key, property);
            delete this.responsiveGlobalSnapshots[key];
            this.onUpdateProperty(key, this.clampResponsiveValue(globalValue, limits.min, limits.max));
        },

        onUpdateResponsiveGlobalProperty(key: string, property: ContentSystemElementTypeProperty, rawValue: unknown): void {
            if (this.isResponsiveViewportMode(key)) {
                return;
            }

            const limits = this.getResponsiveLimits(property);
            const value = this.clampResponsiveValue(this.toNumberOrFallback(rawValue, limits.min), limits.min, limits.max);

            this.onUpdateProperty(key, value);
        },

        onUpdateResponsiveViewportProperty(
            key: string,
            property: ContentSystemElementTypeProperty,
            viewport: ResponsiveViewport,
            rawValue: unknown,
        ): void {
            const limits = this.getResponsiveLimits(property);
            const value = this.clampResponsiveValue(this.toNumberOrFallback(rawValue, limits.min), limits.min, limits.max);
            const current = this.getRawPropertyValue(key);
            const base = this.isResponsiveObjectValue(current)
                ? { ...current }
                : {
                    xs: this.getResponsiveGlobalValue(key, property),
                    sm: this.getResponsiveGlobalValue(key, property),
                    md: this.getResponsiveGlobalValue(key, property),
                    lg: this.getResponsiveGlobalValue(key, property),
                    xl: this.getResponsiveGlobalValue(key, property),
                };

            base[viewport] = value;
            this.onUpdateProperty(key, base);
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

        getRadioPanelOptions(property: ContentSystemElementTypeProperty): RadioPanelOption[] {
            const adminProps = this.getControlProps(property);
            const options = adminProps.options;

            if (Array.isArray(options)) {
                return options
                    .filter((option): option is Record<string, unknown> => typeof option === 'object' && option !== null)
                    .map((option) => {
                        const value = typeof option.value === 'string' ? option.value : '';
                        const label = typeof option.label === 'string' ? option.label : value;

                        return {
                            value,
                            label,
                            icon: typeof option.icon === 'string' ? option.icon : undefined,
                            description: typeof option.description === 'string' ? option.description : undefined,
                            disabled: option.disabled === true,
                        };
                    })
                    .filter((option) => option.value.length > 0);
            }

            if (!Array.isArray(property.enum)) {
                return [];
            }

            return property.enum.map((value) => ({
                value: String(value),
                label: String(value),
            }));
        },

        getRadioPanelOptionId(key: string, optionValue: string): string {
            const normalizedKey = key.replace(/[^a-zA-Z0-9_-]/g, '-');
            const normalizedOptionValue = optionValue.replace(/[^a-zA-Z0-9_-]/g, '-');

            return `sw-experience-studio-radio-panel-${normalizedKey}-${normalizedOptionValue}`;
        },

        getRadioPanelLabelTargetId(key: string, property: ContentSystemElementTypeProperty): string | undefined {
            const options = this.getRadioPanelOptions(property);

            if (options.length === 0) {
                return undefined;
            }

            const currentValue = String(this.getPropertyValue(key, property) ?? '');
            const selectedOption = options.find((option) => option.value === currentValue) ?? options[0];

            return this.getRadioPanelOptionId(key, selectedOption.value);
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

        onUpdateRadioPanelProperty(key: string, value: string): void {
            this.onUpdateProperty(key, value);
        },

        toNumberOrFallback(value: unknown, fallback: number): number {
            if (typeof value === 'number' && Number.isFinite(value)) {
                return value;
            }

            if (typeof value === 'string') {
                const parsed = Number(value);

                if (Number.isFinite(parsed)) {
                    return parsed;
                }
            }

            return fallback;
        },

        clampResponsiveValue(value: number, min: number, max: number): number {
            return Math.min(max, Math.max(min, Math.round(value)));
        },

        isResponsiveObjectValue(value: unknown): value is Partial<ResponsiveValue> {
            return typeof value === 'object' && value !== null && !Array.isArray(value);
        },
    },
});
