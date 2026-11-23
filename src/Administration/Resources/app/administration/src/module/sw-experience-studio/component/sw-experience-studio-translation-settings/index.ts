import type { ContentSystemMappingCandidate } from 'src/core/service/api/content-system-mapping-candidate.api.service';
import type { ContentSystemViolation } from 'src/core/service/api/content-system-layout-draft-mutation.api.service';
import type { LayoutTranslationField } from '../../util/layout-translation.util';
import type { SettingsFieldDefinition } from '../sw-experience-studio-settings-fields';
import {
    anchorLanguageId,
    getElementPropertyStorageKey,
    resolveTranslatableFieldValue,
} from '../../util/element-settings.util';
import { translateContentSystemText } from '../../util/content-system-translation.util';
import template from './sw-experience-studio-translation-settings.html.twig';
import './sw-experience-studio-translation-settings.scss';

const TRANSLATION_FIELDS_BATCH_SIZE = 20;

type TranslationElement = Pick<LayoutTranslationField, 'element' | 'elementType'> & {
    entries: LayoutTranslationField[];
    fields: SettingsFieldDefinition[];
};

/** @private @sw-package discovery */
export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        fields: {
            type: Array as PropType<LayoutTranslationField[]>,
            required: true,
        },
        languageId: {
            type: String,
            default: () => anchorLanguageId(),
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

    data() {
        return { visibleFieldCount: TRANSLATION_FIELDS_BATCH_SIZE };
    },

    computed: {
        visibleFields(): LayoutTranslationField[] {
            return this.fields.slice(0, this.visibleFieldCount);
        },

        visibleElements(): TranslationElement[] {
            const elements = new Map<string, TranslationElement>();
            for (const entry of this.visibleFields) {
                let group = elements.get(entry.element.id);
                if (!group) {
                    group = { element: entry.element, elementType: entry.elementType, entries: [], fields: [] };
                    elements.set(entry.element.id, group);
                }
                group.entries.push(entry);
                group.fields.push(entry.field);
            }
            return Array.from(elements.values());
        },

        hasMoreFields(): boolean {
            return !this.isLoadingTypes && !this.typeLoadError && this.visibleFieldCount < this.fields.length;
        },
    },

    methods: {
        async showMoreFields(): Promise<void> {
            const firstNewFieldIndex = this.visibleFieldCount;
            this.visibleFieldCount += TRANSLATION_FIELDS_BATCH_SIZE;
            await this.$nextTick();
            const element = this.$refs.translationSettings as HTMLElement | undefined;
            const nextField = element?.querySelectorAll<HTMLElement>('.sw-experience-studio-settings-fields__field')[
                firstNewFieldIndex
            ];
            const nextControl = nextField?.querySelector<HTMLElement>(
                'input:not(:disabled):not([type="hidden"]), select:not(:disabled), textarea:not(:disabled), button:not(:disabled), [contenteditable="true"], [tabindex="0"]',
            );
            const focusTarget =
                nextControl ?? nextField?.closest<HTMLElement>('.sw-experience-studio-translation-settings__element');
            focusTarget?.focus();
        },

        getElementLabel(entry: TranslationElement): string {
            return translateContentSystemText(entry.elementType.labelSnippet, entry.elementType.label, this.$te, this.$t);
        },

        getValues(group: TranslationElement): Record<string, unknown> {
            const values: Record<string, unknown> = {};
            for (const { field } of group.entries) {
                const storageKey = getElementPropertyStorageKey(group.elementType, field.key);
                const value = resolveTranslatableFieldValue(
                    field.property,
                    group.element.properties?.[storageKey],
                    this.languageId,
                );
                values[field.key] = value;
            }
            return values;
        },

        getMappings(group: TranslationElement): Record<string, string> {
            const mappings: Record<string, string> = {};
            for (const { field, mappingPath } of group.entries) {
                if (mappingPath !== null) {
                    mappings[field.key] = mappingPath;
                }
            }
            return mappings;
        },

        onUpdateField(group: TranslationElement, payload: { key: string; value: unknown }): void {
            const entry = group.entries.find(({ field }) => field.key === payload.key);
            if (!this.allowEdit || !entry || entry.mappingPath !== null) {
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
