/**
 * @sw-package framework
 */

import { apiUsageMigration, importSource, memberUsage, upgradeGuide } from '../helpers';

const COMPOSITION_FUNCTIONS = [
    'createExtendableSetup',
    'attachOverrides',
    'getExposedProps',
    'overrideComponentSetup',
    'registerNativeExtensionTargets',
    'registerOverrideComponent',
    'getOverrideComponents',
];

const MESSAGE =
    'This Composition API extension system function is private in 6.8. ' +
    'Write a native setup override (<component>.override.vue with swDefineOverride()) or keep Component.override().';

export default apiUsageMigration({
    id: 'api.composition-extension-system',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'The functions of the Composition API extension system are private. Extensions write native setup overrides or keep Options API overrides.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#composition-api-extension-system-is-no-longer-a-public-entry-point'),
        upgradeGuide('UPGRADE-6.8.md#options-api-overrides-stay-supported'),
    ],
    usage: [
        memberUsage({ object: 'Shopware.Component', properties: COMPOSITION_FUNCTIONS, message: MESSAGE }),
        memberUsage({ object: 'Component', properties: COMPOSITION_FUNCTIONS, message: MESSAGE }),
        importSource({
            sources: [
                'src/app/adapter/composition-extension-system',
                'src/core/factory/native-extension-targets',
            ],
            message: MESSAGE,
        }),
    ],
});
