import type {
    ContentSystemElementTypeProperty,
    ContentSystemElementTypeSpecification,
} from 'src/core/service/api/content-system-element-type.api.service';
import type Repository from 'src/core/data/repository.data';
import {
    getAdminUiHelpText,
    getAdminUiProps as getPropertyAdminUiProps,
    getInitialPropertyValue,
    getPropertyControlType,
} from '../../util/element-settings.util';
import { normalizeBoxSpacingCSSValue } from '../../util/box-spacing.util';
import { isViewportSpecificBreakpointMap } from '../../util/style-settings.util';
import template from './sw-experience-studio-settings-fields.html.twig';
import './sw-experience-studio-settings-fields.scss';

type PrimitiveValue = string | number | boolean | null | Record<string, unknown>;
type ResponsiveViewport = 'xs' | 'sm' | 'md' | 'lg' | 'xl' | 'xxl';
type ResponsiveValue = Record<ResponsiveViewport, PrimitiveValue>;
type RadioPanelOption = {
    value: string;
    label: string;
    icon?: string;
    cornerRadius?: string;
    description?: string;
    disabled?: boolean;
};
type SettingsFieldPanel = {
    key: string;
    technicalName: string | null;
    fields: SettingsFieldDefinition[];
};

const DEFAULT_PANEL_KEY = '__default__';
const DEFAULT_PANEL_SNIPPET = 'sw-experience-studio.detail.elementSettings.panelGeneral';

function getStructuredPropertyDefault(property: ContentSystemElementTypeProperty): string | number | boolean | null {
    const defaults = Object.values(property.properties ?? {})
        .map((nestedProperty) => nestedProperty.default)
        .filter((value): value is string | number | boolean => value !== null && value !== undefined);

    if (defaults.length === 0 || !defaults.every((value) => value === defaults[0])) {
        return null;
    }

    return defaults[0];
}

/**
 * @private
 * @sw-package discovery
 */
export type SettingsFieldDefinition = {
    key: string;
    property: ContentSystemElementTypeProperty;
    breakpointAware?: boolean;
};

/**
 * @private
 * @sw-package discovery
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    inject: ['repositoryFactory'],

    props: {
        fields: {
            type: Array as PropType<SettingsFieldDefinition[]>,
            required: true,
        },
        values: {
            type: Object as PropType<Record<string, unknown>>,
            required: true,
        },
        allowEdit: {
            type: Boolean,
            required: false,
            default: false,
        },
        selectedElementType: {
            type: Object,
            required: false,
            default: null,
        },
        isInlineEditingActive: {
            type: Boolean,
            required: false,
            default: false,
        },
        showInlineTextHints: {
            type: Boolean,
            required: false,
            default: false,
        },
        showPanels: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    emits: [
        'update-field',
    ],

    watch: {
        fields: {
            handler() {
                this.syncLoadedResponsiveState();
            },
            immediate: true,
        },

        values: {
            handler() {
                this.syncLoadedResponsiveState();
            },
        },
    },

    data() {
        return {
            expandedResponsiveProperties: {} as Record<string, boolean>,
            responsiveGlobalSnapshots: {} as Record<string, PrimitiveValue>,
            touchedBreakpointAwareProperties: {} as Record<string, boolean>,
        };
    },

    computed: {
        fieldPanels(): SettingsFieldPanel[] {
            if (!this.showPanels) {
                return [
                    {
                        key: DEFAULT_PANEL_KEY,
                        technicalName: null,
                        fields: this.fields,
                    },
                ];
            }

            const panels = new Map<string, SettingsFieldPanel>();

            for (const field of this.fields) {
                const technicalName = this.getFieldPanelTechnicalName(field);
                const key = technicalName ?? DEFAULT_PANEL_KEY;
                const panel = panels.get(key);

                if (panel) {
                    panel.fields.push(field);
                    continue;
                }

                panels.set(key, {
                    key,
                    technicalName,
                    fields: [field],
                });
            }

            return Array.from(panels.values());
        },
    },

    methods: {
        getFieldPanelTechnicalName(field: SettingsFieldDefinition): string | null {
            const panel = field.property.adminUI?.panel;

            return typeof panel === 'string' && panel.length > 0 ? panel : null;
        },

        getPanelSnippetKey(panel: SettingsFieldPanel): string {
            if (panel.technicalName === null) {
                return DEFAULT_PANEL_SNIPPET;
            }

            const selectedElementType = this.selectedElementType as ContentSystemElementTypeSpecification | null;
            const elementType = Shopware.Utils.string.kebabCase(selectedElementType?.name ?? '');

            return `sw-experience-studio.elements.${elementType}.panels.${panel.technicalName}`;
        },

        getPanelTitle(panel: SettingsFieldPanel): string {
            return this.$t(this.getPanelSnippetKey(panel));
        },

        isPanelExpandedByDefault(panel: SettingsFieldPanel): boolean {
            return !this.showPanels || panel.technicalName === null || panel.technicalName === 'general';
        },

        getControlType(property: ContentSystemElementTypeProperty): string | null {
            return getPropertyControlType(property);
        },

        isBreakpointAwareField(field: SettingsFieldDefinition): boolean {
            if (field.breakpointAware === true) {
                return true;
            }

            return this.getControlType(field.property) === 'responsive-number';
        },

        isInlineTextProperty(key: string, property: ContentSystemElementTypeProperty): boolean {
            const selectedElementType = this.selectedElementType as ContentSystemElementTypeSpecification | null;

            if (!this.showInlineTextHints || !selectedElementType || key !== 'text') {
                return false;
            }

            const matchesTextType = selectedElementType.name.endsWith(':text');
            const matchesTextProperty = Boolean(
                selectedElementType.properties.text &&
                    this.getControlType(selectedElementType.properties.text) === 'richtext',
            );

            return (matchesTextType || matchesTextProperty) && this.getControlType(property) === 'richtext';
        },

        getPropertyValue(key: string, property: ContentSystemElementTypeProperty): PrimitiveValue {
            const currentValue = this.values[key];
            const value = getInitialPropertyValue(property, currentValue);

            if (
                value === null &&
                (this.getControlType(property) === 'number' || this.getControlType(property) === 'responsive-number')
            ) {
                return this.getResponsiveFallbackValue(property);
            }

            return value;
        },

        getRawPropertyValue(key: string): unknown {
            return this.values[key];
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

        getResponsiveViewports(field: SettingsFieldDefinition): ResponsiveViewport[] {
            if (field.breakpointAware === true) {
                return [
                    'xs',
                    'sm',
                    'md',
                    'lg',
                    'xl',
                    'xxl',
                ];
            }

            return [
                'xs',
                'sm',
                'md',
                'lg',
                'xl',
            ];
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

        isResponsiveViewportMode(key: string, field: SettingsFieldDefinition): boolean {
            if (this.expandedResponsiveProperties[key]) {
                return true;
            }

            if (field.breakpointAware === true) {
                return isViewportSpecificBreakpointMap(this.getRawPropertyValue(key), this.getResponsiveViewports(field));
            }

            return this.isBreakpointAwareField(field) && this.isResponsiveObjectValue(this.getRawPropertyValue(key));
        },

        getResponsiveGlobalValue(
            key: string,
            property: ContentSystemElementTypeProperty,
            field?: SettingsFieldDefinition,
        ): PrimitiveValue {
            if (field && this.isResponsiveViewportMode(key, field)) {
                const globalSnapshot = this.responsiveGlobalSnapshots[key];

                if (globalSnapshot !== undefined) {
                    return this.normalizeResponsiveValue(property, globalSnapshot);
                }
            }

            return this.resolveResponsiveGlobalValueFromStorage(key, property);
        },

        resolveResponsiveGlobalValueFromStorage(key: string, property: ContentSystemElementTypeProperty): PrimitiveValue {
            const rawValue = this.getRawPropertyValue(key);

            if (this.isResponsiveObjectValue(rawValue)) {
                const fallbackOrder: ResponsiveViewport[] = [
                    'xs',
                    'sm',
                    'md',
                    'lg',
                    'xl',
                    'xxl',
                ];
                for (const viewport of fallbackOrder) {
                    const candidate = rawValue[viewport];
                    if (candidate !== undefined) {
                        return this.normalizeResponsiveValue(property, candidate);
                    }
                }
            }

            if (rawValue !== undefined && !this.isResponsiveObjectValue(rawValue)) {
                return this.normalizeResponsiveValue(property, rawValue);
            }

            return this.getResponsiveFallbackValue(property);
        },

        deriveGlobalSnapshotFromBreakpointMap(
            property: ContentSystemElementTypeProperty,
            field: SettingsFieldDefinition,
            rawValue: Partial<ResponsiveValue>,
        ): PrimitiveValue {
            const resolvedValues = this.getResponsiveViewports(field).map((viewport) => {
                const viewportValue = rawValue[viewport];

                if (viewportValue !== undefined) {
                    return this.normalizeResponsiveValue(property, viewportValue);
                }

                return this.getResponsiveFallbackValue(property);
            });

            const firstValue = resolvedValues[0];

            if (resolvedValues.every((value) => value === firstValue)) {
                return firstValue;
            }

            return this.getResponsiveFallbackValue(property);
        },

        getResponsiveViewportValue(
            key: string,
            viewport: ResponsiveViewport,
            property: ContentSystemElementTypeProperty,
            field: SettingsFieldDefinition,
        ): PrimitiveValue {
            const rawValue = this.getRawPropertyValue(key);

            if (this.isResponsiveObjectValue(rawValue)) {
                const viewportValue = rawValue[viewport];
                if (viewportValue !== undefined) {
                    return this.normalizeResponsiveValue(property, viewportValue);
                }

                if (
                    field.breakpointAware === true &&
                    isViewportSpecificBreakpointMap(rawValue, this.getResponsiveViewports(field))
                ) {
                    return this.getResponsiveFallbackValue(property);
                }
            }

            return this.resolveResponsiveGlobalValueFromStorage(key, property);
        },

        syncLoadedResponsiveState(): void {
            for (const field of this.fields) {
                if (field.breakpointAware !== true) {
                    continue;
                }

                const rawValue = this.getRawPropertyValue(field.key);

                if (rawValue === undefined) {
                    continue;
                }

                this.touchedBreakpointAwareProperties[field.key] = true;

                if (isViewportSpecificBreakpointMap(rawValue, this.getResponsiveViewports(field))) {
                    this.expandedResponsiveProperties[field.key] = true;

                    if (this.responsiveGlobalSnapshots[field.key] === undefined) {
                        this.responsiveGlobalSnapshots[field.key] = this.deriveGlobalSnapshotFromBreakpointMap(
                            field.property,
                            field,
                            rawValue as Partial<ResponsiveValue>,
                        );
                    }
                }
            }
        },

        onToggleResponsiveViewportMode(
            key: string,
            property: ContentSystemElementTypeProperty,
            field: SettingsFieldDefinition,
        ): void {
            const nextState = !this.isResponsiveViewportMode(key, field);

            if (nextState) {
                this.responsiveGlobalSnapshots[key] = this.resolveResponsiveGlobalValueFromStorage(key, property);
                this.expandedResponsiveProperties[key] = true;

                const currentValue = this.getRawPropertyValue(key);

                if (this.isResponsiveObjectValue(currentValue)) {
                    return;
                }

                if (
                    field.breakpointAware === true &&
                    currentValue === undefined &&
                    !this.touchedBreakpointAwareProperties[key]
                ) {
                    return;
                }

                const globalValue = this.responsiveGlobalSnapshots[key];
                const responsiveValue = this.getResponsiveViewports(field).reduce<ResponsiveValue>(
                    (accumulator, viewport) => {
                        accumulator[viewport] = this.normalizeResponsiveValue(property, globalValue);

                        return accumulator;
                    },
                    {} as ResponsiveValue,
                );

                this.onUpdateField(key, responsiveValue);

                return;
            }

            const snapshot = this.responsiveGlobalSnapshots[key];
            const globalValue =
                snapshot !== undefined
                    ? this.normalizeResponsiveValue(property, snapshot)
                    : this.resolveResponsiveGlobalValueFromStorage(key, property);

            delete this.responsiveGlobalSnapshots[key];
            this.expandedResponsiveProperties[key] = false;
            this.persistBreakpointAwareValue(key, property, field, globalValue);
        },

        onUpdateResponsiveGlobalProperty(
            key: string,
            property: ContentSystemElementTypeProperty,
            field: SettingsFieldDefinition,
            rawValue: unknown,
        ): void {
            if (this.isResponsiveViewportMode(key, field)) {
                return;
            }

            if (field.breakpointAware === true) {
                this.touchedBreakpointAwareProperties[key] = true;
            }

            const normalizedValue = this.normalizeResponsiveValue(property, rawValue);
            this.persistBreakpointAwareValue(key, property, field, normalizedValue);
        },

        onUpdateResponsiveViewportProperty(
            key: string,
            property: ContentSystemElementTypeProperty,
            field: SettingsFieldDefinition,
            viewport: ResponsiveViewport,
            rawValue: unknown,
        ): void {
            if (field.breakpointAware === true) {
                this.touchedBreakpointAwareProperties[key] = true;
            }

            const value = this.normalizeResponsiveValue(property, rawValue);
            const current = this.getRawPropertyValue(key);
            const base = this.isResponsiveObjectValue(current) ? { ...current } : {};

            if (field.breakpointAware === true && this.isEffectiveUnsetViewportStyleValue(property, value)) {
                delete base[viewport];
            } else {
                base[viewport] = value;
            }

            if (Object.keys(base).length === 0 || this.isEffectiveUnsetBreakpointMap(field, property, base)) {
                this.onUpdateField(key, null);
                return;
            }

            this.onUpdateField(key, base);
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

        getEntityRepository(entityName: string): Repository<keyof EntitySchema.Entities> {
            return this.repositoryFactory.create(entityName as keyof EntitySchema.Entities);
        },

        getEntityName(property: ContentSystemElementTypeProperty): string | null {
            const entity = property.adminUI?.entity;

            return typeof entity === 'string' && entity.length > 0 ? entity : null;
        },

        getControlProps(property: ContentSystemElementTypeProperty): Record<string, unknown> {
            return getPropertyAdminUiProps(property);
        },

        getPropertyHelpText(property: ContentSystemElementTypeProperty): string | undefined {
            const helpText = getAdminUiHelpText(property);

            if (!helpText) {
                return undefined;
            }

            return this.$te(helpText) ? this.$t(helpText) : helpText;
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
                            cornerRadius: typeof option.cornerRadius === 'string' ? option.cornerRadius : undefined,
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

        getRadioPanelLabelTargetId(
            key: string,
            property: ContentSystemElementTypeProperty,
            field?: SettingsFieldDefinition,
        ): string | undefined {
            const options = this.getRadioPanelOptions(property);

            if (options.length === 0) {
                return undefined;
            }

            const currentValue =
                field && this.isBreakpointAwareField(field)
                    ? this.getResponsiveGlobalValue(key, property, field)
                    : this.getPropertyValue(key, property);
            const normalizedCurrentValue =
                typeof currentValue === 'object' && currentValue !== null ? '' : String(currentValue ?? '');
            const selectedOption = options.find((option) => option.value === normalizedCurrentValue) ?? options[0];

            return this.getRadioPanelOptionId(key, selectedOption.value);
        },

        onUpdateField(key: string, value: PrimitiveValue): void {
            if (!this.allowEdit) {
                return;
            }

            this.$emit('update-field', {
                key,
                value,
            });
        },

        onUpdateRadioPanelProperty(key: string, value: string): void {
            this.onUpdateField(key, value);
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

        normalizeResponsiveValue(property: ContentSystemElementTypeProperty, value: unknown): PrimitiveValue {
            if (this.getControlType(property) === 'box-spacing') {
                return normalizeBoxSpacingCSSValue(value);
            }

            if (this.getControlType(property) === 'responsive-number' || this.getControlType(property) === 'number') {
                const limits = this.getResponsiveLimits(property);

                return this.clampResponsiveValue(this.toNumberOrFallback(value, limits.min), limits.min, limits.max);
            }

            if (this.getControlType(property) === 'switch') {
                return value === true;
            }

            if (this.getControlType(property) === 'radio-panel' || this.getControlType(property) === 'select') {
                if (typeof value === 'string') {
                    return value;
                }

                if (value === null || value === undefined) {
                    return this.getResponsiveFallbackValue(property);
                }
            }

            if (value === null || value === undefined) {
                return this.getResponsiveFallbackValue(property);
            }

            return value as PrimitiveValue;
        },

        getResponsiveFallbackValue(property: ContentSystemElementTypeProperty): PrimitiveValue {
            const initialValue = getInitialPropertyValue(property, undefined);

            if (this.getControlType(property) === 'box-spacing') {
                const structuredDefault = getStructuredPropertyDefault(property);
                if (typeof structuredDefault === 'string' || typeof structuredDefault === 'number') {
                    return normalizeBoxSpacingCSSValue(structuredDefault);
                }

                if (typeof initialValue === 'string' || typeof initialValue === 'number') {
                    return normalizeBoxSpacingCSSValue(initialValue);
                }

                return '';
            }

            if (this.getControlType(property) === 'responsive-number' || this.getControlType(property) === 'number') {
                const limits = this.getResponsiveLimits(property);

                if (typeof initialValue === 'number') {
                    return this.clampResponsiveValue(initialValue, limits.min, limits.max);
                }

                if (typeof property.default === 'number') {
                    return this.clampResponsiveValue(property.default, limits.min, limits.max);
                }

                return limits.min;
            }

            if (typeof property.default === 'string' || typeof property.default === 'boolean') {
                return property.default;
            }

            return initialValue;
        },

        isResponsiveObjectValue(value: unknown): value is Partial<ResponsiveValue> {
            return typeof value === 'object' && value !== null && !Array.isArray(value);
        },

        isEffectiveUnsetStyleValue(
            field: SettingsFieldDefinition,
            property: ContentSystemElementTypeProperty,
            value: PrimitiveValue,
        ): boolean {
            if (field.breakpointAware !== true) {
                return false;
            }

            if (value === null || value === undefined || value === '') {
                return true;
            }

            if (property.default !== null && property.default !== undefined) {
                return value === property.default;
            }

            if (this.getControlType(property) === 'responsive-number') {
                const limits = this.getResponsiveLimits(property);

                return value === limits.min;
            }

            return false;
        },

        isEffectiveUnsetViewportStyleValue(property: ContentSystemElementTypeProperty, value: PrimitiveValue): boolean {
            if (value === null || value === undefined || value === '') {
                return true;
            }

            if (this.getControlType(property) === 'responsive-number') {
                const limits = this.getResponsiveLimits(property);

                return value === limits.min;
            }

            return false;
        },

        isEffectiveUnsetBreakpointMap(
            field: SettingsFieldDefinition,
            property: ContentSystemElementTypeProperty,
            value: Record<string, unknown>,
        ): boolean {
            if (field.breakpointAware !== true) {
                return false;
            }

            const entries = Object.entries(value).filter(
                ([
                    ,
                    entryValue,
                ]) => entryValue !== null && entryValue !== undefined,
            );

            if (entries.length === 0) {
                return true;
            }

            return entries.every(
                ([
                    ,
                    entryValue,
                ]) => this.isEffectiveUnsetViewportStyleValue(property, this.normalizeResponsiveValue(property, entryValue)),
            );
        },

        shouldPersistBreakpointAwareValue(
            key: string,
            field: SettingsFieldDefinition,
            property: ContentSystemElementTypeProperty,
            value: PrimitiveValue,
        ): boolean {
            if (field.breakpointAware !== true) {
                return true;
            }

            const storedValue = this.getRawPropertyValue(key);

            if (storedValue !== undefined) {
                return !this.isEffectiveUnsetStyleValue(field, property, value);
            }

            return (
                this.touchedBreakpointAwareProperties[key] === true &&
                !this.isEffectiveUnsetStyleValue(field, property, value)
            );
        },

        persistBreakpointAwareValue(
            key: string,
            property: ContentSystemElementTypeProperty,
            field: SettingsFieldDefinition,
            value: PrimitiveValue,
        ): void {
            if (field.breakpointAware === true) {
                if (!this.shouldPersistBreakpointAwareValue(key, field, property, value)) {
                    if (this.getRawPropertyValue(key) !== undefined) {
                        this.onUpdateField(key, null);
                    }

                    return;
                }

                this.onUpdateField(key, this.wrapBreakpointAwareGlobalValue(field, value));
                return;
            }

            this.onUpdateField(key, value);
        },

        wrapBreakpointAwareGlobalValue(field: SettingsFieldDefinition, value: PrimitiveValue): Partial<ResponsiveValue> {
            return this.getResponsiveViewports(field).reduce<Partial<ResponsiveValue>>((accumulator, viewport) => {
                accumulator[viewport] = value;

                return accumulator;
            }, {});
        },
    },
});
