import type Repository from 'src/core/data/repository.data';
import type {
    ContentSystemBreakpoint,
    ContentSystemBreakpointFallbacks,
    ContentSystemElementTypeProperty,
    ContentSystemElementTypeSpecification,
} from 'src/core/service/api/content-system-element-type.api.service';
import type { ContentSystemMappingCandidate } from 'src/core/service/api/content-system-mapping-candidate.api.service';
import type { ContentSystemViolation } from 'src/core/service/api/content-system-layout-draft-mutation.api.service';
import {
    getAdminUiHelpText,
    getAdminUiProps as getPropertyAdminUiProps,
    getInitialPropertyValue,
    getPropertyControlType,
} from '../../util/element-settings.util';
import { normalizeBoxSpacingCSSValue } from '../../util/box-spacing.util';
import { resolveBreakpointValue } from '../../util/breakpoint.util';
import {
    getCandidatesForProperty,
    getInlineMappingCandidates,
    getMappingCandidateTranslation,
    isInlineMappableProperty,
    isMappableProperty,
} from '../../util/element-mapping.util';
import { translateContentSystemText } from '../../util/content-system-translation.util';
import BreakpointSelect, { BASE_BREAKPOINT, orderedBreakpoints } from '../sw-experience-studio-breakpoint-select';
import template from './sw-experience-studio-settings-fields.html.twig';
import './sw-experience-studio-settings-fields.scss';

type PrimitiveValue = string | number | boolean | null | Record<string, unknown>;
type ResponsiveValue = Partial<Record<ContentSystemBreakpoint, PrimitiveValue>>;
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
type EntityMultiCodec = 'csv' | 'array';

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

    components: { BreakpointSelect },

    inject: [
        'repositoryFactory',
    ],

    props: {
        fields: {
            type: Array as PropType<SettingsFieldDefinition[]>,
            required: true,
        },
        values: {
            type: Object as PropType<Record<string, unknown>>,
            required: true,
        },
        breakpointFallbacks: {
            type: Object as PropType<ContentSystemBreakpointFallbacks>,
            default: () => ({}),
        },
        languageName: {
            type: String,
            default: '',
        },
        fieldIdPrefix: {
            type: String,
            default: '',
        },
        allowEdit: {
            type: Boolean,
            required: false,
            default: false,
        },
        allowMapping: {
            type: Boolean,
            default: true,
        },
        selectedElementType: {
            type: Object,
            required: false,
            default: null,
        },
        showPanels: {
            type: Boolean,
            required: false,
            default: false,
        },
        mappingCandidates: {
            type: Array as PropType<ContentSystemMappingCandidate[]>,
            required: false,
            default: () => [],
        },
        isLoadingMappingCandidates: {
            type: Boolean,
            required: false,
            default: false,
        },
        mappingCandidateLoadError: {
            type: String,
            required: false,
            default: null,
        },
        /**
         * Catalogue path per mapped property key. A property absent here renders its authored value.
         */
        mappings: {
            type: Object as PropType<Record<string, string>>,
            required: false,
            default: () => ({}),
        },
        violations: {
            type: Array as PropType<ContentSystemViolation[]>,
            required: false,
            default: () => [],
        },
    },

    emits: [
        'update-field',
        'update-mapping',
        'retry-mapping-candidates',
    ],

    data() {
        return {
            radioPanelDescriptionIdPrefix: Shopware.Utils.createId(),
            selectedBreakpoints: {} as Partial<Record<string, ContentSystemBreakpoint>>,
            mappingModalFieldKey: null as string | null,
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

        mappingModalField(): SettingsFieldDefinition | null {
            if (this.mappingModalFieldKey === null) {
                return null;
            }

            return this.fields.find((field) => field.key === this.mappingModalFieldKey) ?? null;
        },

        mappingModalCandidates(): ContentSystemMappingCandidate[] {
            const field = this.mappingModalField;

            return field === null ? [] : this.getMappingCandidatesForField(field);
        },

        /**
         * Computed once for the panel rather than per field: inline mapping fills text rather than a typed slot, so
         * unlike `getMappingCandidatesForField` the narrowing does not depend on which property is being edited.
         */
        inlineMappingCandidates(): ContentSystemMappingCandidate[] {
            return getInlineMappingCandidates(this.mappingCandidates);
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
            const key = this.getPanelSnippetKey(panel);
            const fallbackLocale = Shopware.Context.app.fallbackLocale;

            return this.$te(key) || (fallbackLocale && this.$te(key, fallbackLocale))
                ? this.$t(key)
                : (panel.technicalName ?? '').replace(/[-_]+/g, ' ');
        },

        isPanelExpandedByDefault(panel: SettingsFieldPanel): boolean {
            return !this.showPanels || panel.technicalName === null || panel.technicalName === 'general';
        },

        getControlType(property: ContentSystemElementTypeProperty): string | null {
            return getPropertyControlType(property);
        },

        getMappingCandidatesForField(field: SettingsFieldDefinition): ContentSystemMappingCandidate[] {
            if (!isMappableProperty(field.property)) {
                return [];
            }

            return getCandidatesForProperty(this.mappingCandidates, field.property);
        },

        isFieldMapped(field: SettingsFieldDefinition): boolean {
            return typeof this.mappings[field.key] === 'string';
        },

        isInlineMappableField(field: SettingsFieldDefinition): boolean {
            return isInlineMappableProperty(field.property);
        },

        getFieldMappingPath(field: SettingsFieldDefinition): string | null {
            return this.mappings[field.key] ?? null;
        },

        /**
         * A mapping offered by one root source can survive a layout being pointed at another, so fall back to the
         * stored path when the current catalogue no longer describes it.
         */
        getFieldMappingLabel(field: SettingsFieldDefinition): string {
            const path = this.getFieldMappingPath(field);

            if (path === null) {
                return '';
            }

            const candidate = this.mappingCandidates.find((entry) => entry.path === path);

            if (!candidate) {
                return path;
            }

            const configuredLabel = getMappingCandidateTranslation(
                candidate.labelTranslations,
                Shopware.Store.get('session').currentLocale ?? '',
                Shopware.Context.app.fallbackLocale ?? '',
            );

            if (configuredLabel !== '') {
                return configuredLabel;
            }

            return this.$te(candidate.label) ? this.$t(candidate.label) : candidate.path;
        },

        canMapField(field: SettingsFieldDefinition): boolean {
            return this.allowMapping && !this.isFieldMapped(field) && this.getMappingCandidatesForField(field).length > 0;
        },

        getFieldViolation(field: SettingsFieldDefinition): ContentSystemViolation | null {
            const violations = this.violations.filter((violation) => violation.key === field.key);

            return violations.find((violation) => violation.severity === 'error') ?? violations[0] ?? null;
        },

        onOpenMappingModal(field: SettingsFieldDefinition): void {
            if (!this.allowEdit) {
                return;
            }

            this.mappingModalFieldKey = field.key;
        },

        onCloseMappingModal(): void {
            this.mappingModalFieldKey = null;
        },

        onRetryMappingCandidates(): void {
            this.$emit('retry-mapping-candidates');
        },

        onSelectMapping(candidate: ContentSystemMappingCandidate): void {
            const field = this.mappingModalField;

            this.mappingModalFieldKey = null;

            if (field === null || !this.allowEdit) {
                return;
            }

            this.$emit('update-mapping', {
                key: field.key,
                source: candidate.source,
                contextType: candidate.contextType,
            });
        },

        onUnmapField(field: SettingsFieldDefinition): void {
            if (!this.allowEdit) {
                return;
            }

            this.$emit('update-mapping', {
                key: field.key,
                source: null,
                contextType: null,
            });
        },

        isBreakpointAwareField(field: SettingsFieldDefinition): boolean {
            if (field.breakpointAware === true) {
                return true;
            }

            return this.getControlType(field.property) === 'responsive-number';
        },

        getPropertyValue(key: string, property: ContentSystemElementTypeProperty): PrimitiveValue {
            const currentValue = this.values[key];
            const value = getInitialPropertyValue(property, currentValue);

            if (
                value === null &&
                !property.translatable &&
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

        getSelectedBreakpoint(key: string): ContentSystemBreakpoint {
            return this.selectedBreakpoints[key] ?? BASE_BREAKPOINT;
        },

        getResponsiveOverrides(key: string): ContentSystemBreakpoint[] {
            const value = this.getRawPropertyValue(key);
            if (!this.isResponsiveObjectValue(value)) {
                return [];
            }

            return orderedBreakpoints.filter((breakpoint) => breakpoint !== BASE_BREAKPOINT && value[breakpoint] != null);
        },

        getResponsiveValue(field: SettingsFieldDefinition): PrimitiveValue {
            const value = this.getRawPropertyValue(field.key);
            const resolved = this.isResponsiveObjectValue(value)
                ? resolveBreakpointValue(value, this.getSelectedBreakpoint(field.key), this.breakpointFallbacks)
                : value;

            return resolved == null
                ? this.getResponsiveFallbackValue(field.property)
                : this.normalizeResponsiveValue(field.property, resolved);
        },

        onUpdateResponsiveProperty(field: SettingsFieldDefinition, rawValue: unknown): void {
            if (!this.allowEdit) {
                return;
            }

            const breakpoint = this.getSelectedBreakpoint(field.key);
            const stored = this.getRawPropertyValue(field.key);
            const value: ResponsiveValue = this.isResponsiveObjectValue(stored) ? { ...stored } : {};

            if (!this.isResponsiveObjectValue(stored) && stored != null) {
                value[BASE_BREAKPOINT] = this.normalizeResponsiveValue(field.property, stored);
            }

            if (rawValue == null || rawValue === '') {
                delete value[breakpoint];
            } else {
                value[breakpoint] = this.normalizeResponsiveValue(field.property, rawValue);
            }

            this.onUpdateField(field.key, Object.keys(value).length > 0 ? value : null);
        },

        getSelectOptions(property: ContentSystemElementTypeProperty): Array<Record<string, unknown>> {
            const options = this.getControlProps(property).options;

            if (Array.isArray(options)) {
                return options.filter(
                    (option): option is Record<string, unknown> => typeof option === 'object' && option !== null,
                );
            }

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

        getEntityRepository(entityName: string): Repository<keyof EntitySchema.EntityKeys> {
            return this.repositoryFactory.create(entityName as keyof EntitySchema.EntityKeys);
        },

        getEntityMultiCodec(field: SettingsFieldDefinition): EntityMultiCodec | null {
            const selectedElementType = this.selectedElementType as ContentSystemElementTypeSpecification | null;
            const storesIdArray = Object.values(selectedElementType?.bindingSpecifications ?? {}).some(
                (bindingSpecification) =>
                    bindingSpecification.default && bindingSpecification.resolves[field.key]?.loader === 'entity_collection',
            );

            if (storesIdArray) {
                return 'array';
            }

            const propertyType = field.property.type;
            const storesCommaSeparatedIds = Array.isArray(propertyType)
                ? propertyType.includes('string')
                : propertyType === 'string';

            return storesCommaSeparatedIds ? 'csv' : null;
        },

        getEntityMultiValue(key: string): string[] {
            const rawValue = this.getRawPropertyValue(key);

            if (typeof rawValue === 'string') {
                return rawValue.split(',').filter((id) => id.length > 0);
            }

            if (Array.isArray(rawValue)) {
                return rawValue.filter((id): id is string => typeof id === 'string');
            }

            return [];
        },

        onUpdateEntityMultiField(field: SettingsFieldDefinition, ids: string[]): void {
            const codec = this.getEntityMultiCodec(field);

            if (codec === 'csv') {
                this.onUpdateField(field.key, ids.join(','));
                return;
            }

            if (codec === 'array') {
                this.onUpdateField(field.key, ids);
            }
        },

        getPropertyTitle(property: ContentSystemElementTypeProperty): string {
            return translateContentSystemText(property.titleSnippet, property.title, this.$te, this.$t);
        },

        getControlProps(property: ContentSystemElementTypeProperty): Record<string, unknown> {
            const props = getPropertyAdminUiProps(property);
            const translated = { ...props };
            if (typeof props.placeholder === 'string') {
                translated.placeholder = translateContentSystemText(
                    props.placeholderSnippet,
                    props.placeholder,
                    this.$te,
                    this.$t,
                );
            }
            if (Array.isArray(props.options)) {
                translated.options = props.options.map((option: unknown) => {
                    if (typeof option !== 'object' || option === null) {
                        return option;
                    }
                    const item = option as Record<string, unknown>;
                    return {
                        ...item,
                        ...(typeof item.label === 'string'
                            ? {
                                  label: translateContentSystemText(item.labelSnippet, item.label, this.$te, this.$t),
                              }
                            : {}),
                        ...(typeof item.description === 'string'
                            ? {
                                  description: translateContentSystemText(
                                      item.descriptionSnippet,
                                      item.description,
                                      this.$te,
                                      this.$t,
                                  ),
                              }
                            : {}),
                    };
                });
            }
            return translated;
        },

        getPropertyHelpText(property: ContentSystemElementTypeProperty): string | undefined {
            const helpText = getAdminUiHelpText(property);

            if (!helpText) {
                return undefined;
            }

            if (property.adminUI?.helpTextSnippet) {
                return translateContentSystemText(property.adminUI.helpTextSnippet, helpText, this.$te, this.$t);
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
                            disabled: typeof option.disabled === 'boolean' ? option.disabled : undefined,
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

        getRadioPanelDescription(property: ContentSystemElementTypeProperty, value: unknown): string | undefined {
            const selected =
                typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean' ? String(value) : '';
            return this.getRadioPanelOptions(property).find((option) => option.value === selected)?.description;
        },

        getRadioPanelDescriptionId(key: string): string {
            return `sw-experience-studio-radio-panel-description-${this.radioPanelDescriptionIdPrefix}-${encodeURIComponent(key)}`;
        },

        getRadioPanelOptionId(key: string, optionValue: string): string {
            return `sw-experience-studio-radio-panel-${this.radioPanelDescriptionIdPrefix}-${encodeURIComponent(JSON.stringify([key, optionValue]))}`;
        },

        getFieldControlKey(key: string): string {
            return this.fieldIdPrefix ? `${this.fieldIdPrefix}-${key}` : key;
        },

        getRadioPanelLabelTargetId(key: string, property: ContentSystemElementTypeProperty): string | undefined {
            const options = this.getRadioPanelOptions(property);

            if (options.length === 0) {
                return undefined;
            }

            const currentValue = this.getPropertyValue(key, property);
            const normalizedCurrentValue =
                typeof currentValue === 'object' && currentValue !== null ? '' : String(currentValue ?? '');
            const selectedOption = options.find((option) => option.value === normalizedCurrentValue) ?? options[0];

            return this.getRadioPanelOptionId(key, selectedOption.value);
        },

        onUpdateField(key: string, value: unknown): void {
            if (!this.allowEdit) {
                return;
            }

            this.$emit('update-field', {
                key,
                value,
            });
        },

        // Option keys are strings for the DOM; the emitted value is the declared enum entry, so an
        // integer or boolean enum is written in its declared type.
        getRadioPanelEmitValue(
            key: string,
            property: ContentSystemElementTypeProperty,
            optionKey: string,
        ): string | number | boolean {
            if (!Array.isArray(property.enum)) {
                // Without an enum the choices come from adminUI.props.options, whose keys are the string values.
                if (property.type === 'string') {
                    return optionKey;
                }

                throw new Error(
                    `The radio-panel property "${key}" of type ${JSON.stringify(property.type)} declares no enum, so option "${optionKey}" has no value to emit.`,
                );
            }

            const enumValue = property.enum.find((value) => String(value) === optionKey);

            if (enumValue === undefined) {
                throw new Error(`The option "${optionKey}" of the radio-panel property "${key}" matches no enum entry.`);
            }

            return enumValue;
        },

        onUpdateRadioPanelProperty(key: string, property: ContentSystemElementTypeProperty, optionKey: string): void {
            this.onUpdateField(key, this.getRadioPanelEmitValue(key, property, optionKey));
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

                if (typeof property.default === 'string' || typeof property.default === 'number') {
                    return normalizeBoxSpacingCSSValue(property.default);
                }

                return normalizeBoxSpacingCSSValue(null);
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
    },
});
