import type {
    ContentSystemElementTypeProperty,
    ContentSystemElementTypeSpecification,
} from 'src/core/service/api/content-system-element-type.api.service';

/** @private @sw-package discovery */
export function translationProperty(
    overrides: Partial<ContentSystemElementTypeProperty> = {},
): ContentSystemElementTypeProperty {
    return {
        type: 'string',
        contextTypes: ['single'],
        translatable: true,
        enum: null,
        default: null,
        required: false,
        mappable: false,
        inlineMappable: false,
        defaultMapping: null,
        title: 'Title',
        description: '',
        adminUI: null,
        ...overrides,
    };
}

/** @private @sw-package discovery */
export function translationType(
    properties: Record<string, ContentSystemElementTypeProperty>,
): ContentSystemElementTypeSpecification {
    return {
        name: 'Sw:TranslationTest',
        label: 'Text',
        description: '',
        source: 'core',
        icon: 'regular-align-left',
        illustration: null,
        category: null,
        priority: 0,
        copilot: { summary: '', hints: [] },
        slots: [],
        defaultBindingRootSources: null,
        properties,
    };
}
