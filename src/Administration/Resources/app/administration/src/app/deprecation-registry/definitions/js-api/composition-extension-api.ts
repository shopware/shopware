/**
 * @sw-package framework
 */

import { importSource, jsApiMigration, memberUsage, upgradeGuide } from '../helpers';

export default jsApiMigration({
    id: 'js-api.composition-extension-api',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'The Composition API extension system functions are private. Write a native setup override or keep Component.override().',
    references: [upgradeGuide('UPGRADE-6.8.md#composition-api-extension-system-is-no-longer-a-public-entry-point')],
    usage: [
        memberUsage({
            from: 'Shopware.Component.createExtendableSetup',
            to: 'a native setup override (<component>.override.vue with swDefineOverride())',
        }),
        memberUsage({
            from: 'Shopware.Component.attachOverrides',
            to: 'a native setup override (<component>.override.vue with swDefineOverride())',
        }),
        memberUsage({
            from: 'Shopware.Component.getExposedProps',
            to: 'a native setup override (<component>.override.vue with swDefineOverride())',
        }),
        memberUsage({
            from: 'Shopware.Component.overrideComponentSetup',
            to: 'a native setup override (<component>.override.vue with swDefineOverride())',
        }),
        memberUsage({
            from: 'Shopware.Component.registerNativeExtensionTargets',
            to: 'a native setup override (<component>.override.vue with swDefineOverride())',
        }),
        memberUsage({
            from: 'Shopware.Component.registerOverrideComponent',
            to: 'a native setup override (<component>.override.vue with swDefineOverride())',
        }),
        memberUsage({
            from: 'Shopware.Component.getOverrideComponents',
            to: 'a native setup override (<component>.override.vue with swDefineOverride())',
        }),
        memberUsage({
            from: 'Component.createExtendableSetup',
            to: 'a native setup override (<component>.override.vue with swDefineOverride())',
        }),
        memberUsage({
            from: 'Component.attachOverrides',
            to: 'a native setup override (<component>.override.vue with swDefineOverride())',
        }),
        memberUsage({
            from: 'Component.getExposedProps',
            to: 'a native setup override (<component>.override.vue with swDefineOverride())',
        }),
        memberUsage({
            from: 'Component.overrideComponentSetup',
            to: 'a native setup override (<component>.override.vue with swDefineOverride())',
        }),
        importSource({
            from: 'src/app/adapter/composition-extension-system',
            to: 'a native setup override (<component>.override.vue with swDefineOverride())',
        }),
        importSource({
            from: 'src/core/factory/native-extension-targets',
            to: 'a native setup override (<component>.override.vue with swDefineOverride())',
        }),
    ],
});
