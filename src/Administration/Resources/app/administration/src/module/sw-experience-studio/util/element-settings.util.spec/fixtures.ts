import type { ContentSystemElementTypeProperty } from 'src/core/service/api/content-system-element-type.api.service';

/** @private @sw-package discovery */
export const stringProperty: ContentSystemElementTypeProperty = {
    type: 'string',
    contextTypes: ['single'],
    translatable: false,
    enum: null,
    default: null,
    required: false,
    mappable: false,
    inlineMappable: false,
    defaultMapping: null,
    title: 'Headline',
    description: 'Headline text',
    adminUI: null,
};
