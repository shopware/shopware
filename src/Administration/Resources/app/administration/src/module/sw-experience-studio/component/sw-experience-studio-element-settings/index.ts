import type { ContentElementNode } from 'src/core/service/content-element.types';
import type { ContentSystemElementTypeSpecification } from 'src/core/service/api/content-system-element-type.api.service';
import type { ContentSystemStyleOptionSpecification } from 'src/core/service/api/content-system-style-option.api.service';
import type { SettingsFieldDefinition } from '../sw-experience-studio-settings-fields';
import {
    getElementPropertyStorageKey,
    getInitialPropertyValue,
    getPropertyControlType,
    isPropertyVisible,
} from '../../util/element-settings.util';
import { getEditableStyleFields } from '../../util/style-settings.util';
import template from './sw-experience-studio-element-settings.html.twig';
import './sw-experience-studio-element-settings.scss';

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
            type: Object as PropType<ContentElementNode | null>,
            required: false,
            default: null,
        },
        selectedElementType: {
            type: Object,
            required: false,
            default: null,
        },
        styleOptions: {
            type: Object,
            required: false,
            default: () => ({}),
        },
        isLoadingTypes: {
            type: Boolean,
            required: false,
            default: false,
        },
        isLoadingStyleOptions: {
            type: Boolean,
            required: false,
            default: false,
        },
        typeLoadError: {
            type: String,
            required: false,
            default: null,
        },
        styleOptionLoadError: {
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
        'update-style',
    ],

    data() {
        return {
            activeSettingsTab: 'element' as 'element' | 'layout',
        };
    },

    computed: {
        hasSelectedElement(): boolean {
            return this.selectedElementId !== null;
        },

        hasTypeLoadError(): boolean {
            return typeof this.typeLoadError === 'string' && this.typeLoadError.length > 0;
        },

        hasStyleOptionLoadError(): boolean {
            return typeof this.styleOptionLoadError === 'string' && this.styleOptionLoadError.length > 0;
        },

        hasSelectedElementType(): boolean {
            return this.selectedElementType !== null;
        },

        isLoadingSettings(): boolean {
            return this.isLoadingTypes || this.isLoadingStyleOptions;
        },

        elementPropertyValues(): Record<string, unknown> {
            const typeSpecification = this.selectedElementType as ContentSystemElementTypeSpecification | null;
            const properties = this.selectedElement?.properties ?? {};
            const values = { ...properties };

            if (!typeSpecification) {
                return values;
            }

            for (const key of Object.keys(typeSpecification.properties)) {
                const storageKey = getElementPropertyStorageKey(typeSpecification, key);

                if (storageKey !== key && Object.prototype.hasOwnProperty.call(properties, storageKey)) {
                    values[key] = properties[storageKey];
                }
            }

            return values;
        },

        elementStyleValues(): Record<string, unknown> {
            return this.selectedElement?.style ?? {};
        },

        elementFields(): SettingsFieldDefinition[] {
            const typeSpecification = this.selectedElementType as ContentSystemElementTypeSpecification | null;
            const selectedElement = this.selectedElement;

            if (!typeSpecification) {
                return [];
            }

            const resolvedPropertyValues = Object.entries(typeSpecification.properties).reduce<Record<string, unknown>>(
                (
                    accumulator,
                    [
                        key,
                        property,
                    ],
                ) => {
                    const storageKey = getElementPropertyStorageKey(typeSpecification, key);
                    const elementProperties = selectedElement?.properties ?? {};
                    const currentValue = Object.prototype.hasOwnProperty.call(elementProperties, storageKey)
                        ? elementProperties[storageKey]
                        : elementProperties[key];
                    accumulator[key] = getInitialPropertyValue(property, currentValue);

                    return accumulator;
                },
                {},
            );

            return Object.entries(typeSpecification.properties)
                .filter(
                    ([
                        ,
                        property,
                    ]) => getPropertyControlType(property) !== null,
                )
                .filter(
                    ([
                        ,
                        property,
                    ]) => isPropertyVisible(property, resolvedPropertyValues),
                )
                .map(
                    ([
                        key,
                        property,
                    ]) => ({
                        key,
                        property,
                        breakpointAware: property.adminUI?.breakpointAware === true,
                    }),
                );
        },

        layoutFields(): SettingsFieldDefinition[] {
            const styleOptions = this.styleOptions as Record<string, ContentSystemStyleOptionSpecification>;

            return getEditableStyleFields(styleOptions, this.elementStyleValues).map((field) => ({
                key: field.key,
                property: field.property,
                breakpointAware: field.breakpointAware,
            }));
        },

        showElementEmptyState(): boolean {
            return this.hasSelectedElementType && this.elementFields.length === 0;
        },

        showLayoutEmptyState(): boolean {
            return !this.hasStyleOptionLoadError && this.layoutFields.length === 0;
        },

        settingsTabItems(): Array<{ name: 'element' | 'layout'; label: string }> {
            return [
                {
                    name: 'element',
                    label: this.$t('sw-experience-studio.detail.elementSettings.tabElement'),
                },
                {
                    name: 'layout',
                    label: this.$t('sw-experience-studio.detail.elementSettings.tabLayout'),
                },
            ];
        },
    },

    watch: {
        selectedElementId() {
            this.activeSettingsTab = 'element';
        },
    },

    methods: {
        onSettingsTabChange(tabName: string): void {
            if (tabName === 'element' || tabName === 'layout') {
                this.activeSettingsTab = tabName;
            }
        },

        onUpdateElementField(payload: { key: string; value: unknown }): void {
            const selectedElement = this.selectedElement;

            if (!selectedElement || !this.allowEdit) {
                return;
            }

            const typeSpecification = this.selectedElementType as ContentSystemElementTypeSpecification | null;
            const storageKey = typeSpecification
                ? getElementPropertyStorageKey(typeSpecification, payload.key)
                : payload.key;

            this.$emit('update-properties', {
                elementId: selectedElement.id,
                properties: {
                    [storageKey]: payload.value,
                },
            });
        },

        onUpdateLayoutField(payload: { key: string; value: unknown }): void {
            const selectedElement = this.selectedElement;

            if (!selectedElement || !this.allowEdit) {
                return;
            }

            this.$emit('update-style', {
                elementId: selectedElement.id,
                style: {
                    [payload.key]: payload.value,
                },
            });
        },
    },
});
