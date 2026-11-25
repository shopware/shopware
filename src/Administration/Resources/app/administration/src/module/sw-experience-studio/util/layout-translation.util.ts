import type { ContentElementNode } from 'src/core/service/content-element.types';
import type { ContentSystemElementTypeSpecification } from 'src/core/service/api/content-system-element-type.api.service';
import type { SettingsFieldDefinition } from '../component/sw-experience-studio-settings-fields';
import { getPropertyControlType } from './element-settings.util';
import { findPropertyMapping } from './element-mapping.util';
import { walkContentElements } from './content-element.util';

/** @private @sw-package discovery */
export type LayoutTranslationField = {
    element: ContentElementNode;
    elementType: ContentSystemElementTypeSpecification;
    field: SettingsFieldDefinition;
    mappingPath: string | null;
};

/** @private @sw-package discovery */
export function getLayoutTranslationFields(
    elements: ContentElementNode[],
    getType: (name: string) => ContentSystemElementTypeSpecification | null,
): LayoutTranslationField[] {
    const fields: LayoutTranslationField[] = [];

    walkContentElements(elements, (element) => {
        const elementType = getType(element.component);
        for (const [key, property] of Object.entries(elementType?.properties ?? {})) {
            if (!elementType || !property.translatable || getPropertyControlType(property) === null) {
                continue;
            }
            fields.push({
                element,
                elementType,
                field: { key, property },
                mappingPath: findPropertyMapping(element, key)?.path ?? null,
            });
        }
    });

    return fields;
}
