import { defineDeprecations, reference, templateEventMigration, templateEventUsage } from './helpers';

export default defineDeprecations({
    templateEventMigrations: [
        templateEventMigration({
            id: 'template-event.vue3-v-model-events',
            deprecatedIn: '6.5.0',
            removedIn: '6.6.0',
            description:
                'Legacy Vue 2 value/change/input component events are deprecated. Use the Vue 3 update:* event emitted by the component model.',
            references: [
                reference({ type: 'upgrade', target: 'UPGRADE-6.6.md#administration' }),
            ],
            usage: [
                templateEventUsage({ component: 'sw-text-field', from: 'input', to: 'update:value' }),
                templateEventUsage({
                    component: 'sw-boolean-radio-groups',
                    from: 'change',
                    to: 'update:value',
                }),
                templateEventUsage({
                    component: 'sw-bulk-edit-change-type',
                    from: 'change',
                    to: 'update:value',
                }),
                templateEventUsage({
                    component: 'sw-custom-entity-input-field',
                    from: 'change',
                    to: 'update:value',
                }),
                templateEventUsage({
                    component: 'sw-entity-many-to-many-select',
                    from: 'change',
                    to: 'update:entityCollection',
                }),
                templateEventUsage({
                    component: 'sw-entity-multi-id-select',
                    from: 'change',
                    to: 'update:ids',
                }),
                templateEventUsage({
                    component: 'sw-extension-rating-stars',
                    from: 'rating-changed',
                    to: 'update:rating',
                }),
                templateEventUsage({
                    component: 'sw-extension-select-rating',
                    from: 'change',
                    to: 'update:value',
                }),
                templateEventUsage({ component: 'sw-file-input', from: 'change', to: 'update:value' }),
                templateEventUsage({ component: 'sw-gtc-checkbox', from: 'change', to: 'update:value' }),
                templateEventUsage({
                    component: 'sw-many-to-many-assignment-card',
                    from: 'change',
                    to: 'update:entityCollection',
                }),
                templateEventUsage({
                    component: 'sw-meteor-single-select',
                    from: 'change',
                    to: 'update:value',
                }),
                templateEventUsage({ component: 'sw-multi-select', from: 'change', to: 'update:value' }),
                templateEventUsage({
                    component: 'sw-multi-tag-select',
                    from: 'change',
                    to: 'update:value',
                }),
                templateEventUsage({ component: 'sw-price-field', from: 'change', to: 'update:price' }),
                templateEventUsage({ component: 'sw-radio-panel', from: 'input', to: 'update:value' }),
                templateEventUsage({ component: 'sw-select-field', from: 'change', to: 'update:value' }),
                templateEventUsage({
                    component: 'sw-select-number-field',
                    from: 'change',
                    to: 'update:value',
                }),
                templateEventUsage({ component: 'sw-single-select', from: 'change', to: 'update:value' }),
                templateEventUsage({ component: 'sw-tagged-field', from: 'change', to: 'update:value' }),
                templateEventUsage({ component: 'sw-textarea-field', from: 'input', to: 'update:value' }),
                templateEventUsage({ component: 'sw-url-field', from: 'input', to: 'update:value' }),
                templateEventUsage({
                    component: 'sw-button-process',
                    from: 'process-finish',
                    to: 'update:processSuccess',
                }),
                templateEventUsage({
                    component: 'sw-import-export-entity-path-select',
                    from: 'change',
                    to: 'update:value',
                }),
                templateEventUsage({ component: 'sw-inherit-wrapper', from: 'input', to: 'update:value' }),
                templateEventUsage({
                    component: 'sw-media-breadcrumbs',
                    from: 'media-folder-change',
                    to: 'update:currentFolderId',
                }),
                templateEventUsage({
                    component: 'sw-media-library',
                    from: 'media-selection-change',
                    to: 'update:selection',
                }),
                templateEventUsage({
                    component: 'sw-multi-snippet-drag-and-drop',
                    from: 'change',
                    to: 'update:value',
                }),
                templateEventUsage({
                    component: 'sw-order-customer-address-select',
                    from: 'change',
                    to: 'update:value',
                }),
                templateEventUsage({
                    component: 'sw-order-select-document-type-modal',
                    from: 'change',
                    to: 'update:value',
                }),
                templateEventUsage({ component: 'sw-password-field', from: 'input', to: 'update:value' }),
                templateEventUsage({
                    component: 'sw-promotion-v2-rule-select',
                    from: 'change',
                    to: 'update:collection',
                }),
                templateEventUsage({ component: 'sw-radio-field', from: 'change', to: 'update:value' }),
            ],
        }),
    ],
});
