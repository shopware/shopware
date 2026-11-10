/**
 * @sw-package framework
 */

import { apiUsageMigration, removedComponent, upgradeGuide } from '../helpers';

/**
 * Overrides and extensions of components that other registry categories convert in templates:
 * the component migrations replace their elements, these steps catch `Component.override()` and
 * `Component.extend()` of them, which no replacement can take.
 */
export default [
    apiUsageMigration({
        id: 'api.sw-text-editor.overrides',
        deprecatedIn: '6.7.0',
        removedIn: '6.8.0',
        description: 'sw-text-editor and its toolbar components are removed in favor of mt-text-editor.',
        references: [
            upgradeGuide('UPGRADE-6.8.md#removed-sw-text-editor'),
        ],
        usage: [
            removedComponent({
                names: ['sw-text-editor'],
                inTemplates: false,
                message:
                    'sw-text-editor is removed in 6.8. Use mt-text-editor with v-model, custom-buttons instead of button-config, ' +
                    'and createTextEditorDataMappingButton() of the component helper for data mapping.',
            }),
            removedComponent({
                names: [
                    'sw-text-editor-toolbar',
                    'sw-text-editor-toolbar-button',
                    'sw-text-editor-toolbar-table-button',
                    'sw-text-editor-table-toolbar',
                    'sw-text-editor-link-menu',
                ],
                inTemplates: false,
                message:
                    'The sw-text-editor toolbar components are removed in 6.8. Add buttons with the custom-buttons prop of mt-text-editor.',
            }),
        ],
    }),
    apiUsageMigration({
        id: 'api.sw-popover.overrides',
        deprecatedIn: '6.7.0',
        removedIn: '6.8.0',
        description: 'sw-popover is removed in favor of mt-floating-ui.',
        references: [
            upgradeGuide('UPGRADE-6.8.md#removed-sw-popover'),
        ],
        usage: [
            removedComponent({
                names: ['sw-popover'],
                inTemplates: false,
                message:
                    'sw-popover is removed in 6.8. Use mt-floating-ui: the reference element goes into #trigger, bind :is-opened, ' +
                    'resize-width becomes match-reference-width, and floating-ui-options replaces z-index and popover-config-extension.',
            }),
        ],
    }),
    apiUsageMigration({
        id: 'api.sw-help-sidebar',
        deprecatedIn: '6.7.0',
        removedIn: '6.8.0',
        description: 'sw-help-sidebar is removed without replacement. The help center lives in sw-help-center-v2.',
        references: [
            upgradeGuide('UPGRADE-6.8.md#removed-sw-select-number-field-sw-entity-many-to-many-select-and-sw-help-sidebar'),
        ],
        usage: [
            removedComponent({
                names: ['sw-help-sidebar'],
                message:
                    'sw-help-sidebar is removed in 6.8. The help center lives in sw-help-center-v2, which has the same block names ' +
                    'but renders mt-action-menu-item elements. Move your override there or remove it.',
            }),
        ],
    }),
];
