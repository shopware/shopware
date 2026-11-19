import type { ContentSystemMappingCandidate } from 'src/core/service/api/content-system-mapping-candidate.api.service';
import type { ContentSystemViolation } from 'src/core/service/api/content-system-layout-draft-mutation.api.service';
import type { LayoutTranslationField } from '../../util/layout-translation.util';
import {
    editingLanguageChain,
    getElementPropertyStorageKey,
    resolveTranslatableFieldValue,
} from '../../util/element-settings.util';
import { translateContentSystemText } from '../../util/content-system-translation.util';
import template from './sw-experience-studio-translation-settings.html.twig';
import './sw-experience-studio-translation-settings.scss';

/** @private @sw-package discovery */
export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        fields: {
            type: Array as PropType<LayoutTranslationField[]>,
            required: true,
        },
        languageChain: {
            type: Array as PropType<readonly string[]>,
            default: () => editingLanguageChain(),
        },
        languageName: {
            type: String,
            default: '',
        },
        allowEdit: {
            type: Boolean,
            default: false,
        },
        isLoadingTypes: {
            type: Boolean,
            default: false,
        },
        typeLoadError: {
            type: String,
            default: null,
        },
        mappingCandidates: {
            type: Array as PropType<ContentSystemMappingCandidate[]>,
            default: () => [],
        },
        violations: {
            type: Array as PropType<ContentSystemViolation[]>,
            default: () => [],
        },
    },

    emits: ['update-properties', 'focus-element'],

    methods: {
        getElementLabel(entry: LayoutTranslationField): string {
            const name = translateContentSystemText(
                entry.elementType.labelSnippet,
                entry.elementType.label,
                this.$te,
                this.$t,
            );
            return this.$t('sw-experience-studio.detail.translationSettings.elementLabel', {
                name,
                number: entry.elementNumber,
            });
        },

        getValues(entry: LayoutTranslationField): Record<string, unknown> {
            const key = entry.field.key;
            const storageKey = getElementPropertyStorageKey(entry.elementType, key);
            const value = resolveTranslatableFieldValue(
                entry.field.property,
                entry.element.properties?.[storageKey],
                this.languageChain,
            );
            return value === undefined ? {} : { [key]: value };
        },

        getMappings(entry: LayoutTranslationField): Record<string, string> {
            return entry.mappingPath === null ? {} : { [entry.field.key]: entry.mappingPath };
        },

        onUpdateField(entry: LayoutTranslationField, payload: { key: string; value: unknown }): void {
            if (!this.allowEdit || entry.mappingPath !== null || payload.key !== entry.field.key) {
                return;
            }
            this.$emit('update-properties', {
                elementId: entry.element.id,
                propertyKey: getElementPropertyStorageKey(entry.elementType, entry.field.key),
                value: payload.value,
            });
        },
    },
});
