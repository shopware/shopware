/**
 * @sw-package framework
 *
 * The functions of the Composition API extension system that 6.8 makes private on `Shopware.Component`, with the
 * text the `composition-api-private` codemod writes above their calls. Taken from `src/core/shopware.ts` and the
 * `@private` markers in `src/app/adapter/composition-extension-system/index.ts`. The build transform of native setup
 * SFCs (`<name>.vue`, `<name>.override.vue`) generates these calls; extensions write the SFCs instead.
 *
 * `Shopware.Component.override()` stays: no core component is a native setup SFC in 6.8.
 */

const BASE_SFC = 'Write the component as a native setup SFC (<name>.vue with <script setup> and swDefinePublic({ … })); the build generates this call.';
const OVERRIDE_SFC = 'Write the override as a native override SFC (<component>.override.vue); the build generates this call.';

/** Function name → comment text. `overrideComponentSetup` gets its text from `overrideMessage()`. */
const functions = {
    createExtendableSetup: `Shopware.Component.createExtendableSetup() is private in 6.8. ${BASE_SFC}`,
    attachOverrides: `Shopware.Component.attachOverrides() is private in 6.8. ${BASE_SFC}`,
    getExposedProps: `Shopware.Component.getExposedProps() is private in 6.8. ${BASE_SFC}`,
    registerNativeExtensionTargets: `Shopware.Component.registerNativeExtensionTargets() is private in 6.8. ${OVERRIDE_SFC}`,
    registerOverrideComponent: `Shopware.Component.registerOverrideComponent() is private in 6.8. ${OVERRIDE_SFC}`,
    getOverrideComponents: `Shopware.Component.getOverrideComponents() is private in 6.8. ${OVERRIDE_SFC}`,
};

/**
 * @param {string|null} component the target of `overrideComponentSetup()(name, …)`, when it is a literal
 * @returns {string}
 */
function overrideMessage(component) {
    return (
        'The Composition API extension system is private in 6.8. ' +
        `Move this override to ${component ?? '<component>'}.override.vue ` +
        '(<script setup>, useSwPreviousState(), swDefineOverride({ … })).'
    );
}

module.exports = {
    id: 'composition-api-private',
    description: 'Marks calls of the Composition API extension system, which 6.8 makes private',
    functions,
    overrideFunction: 'overrideComponentSetup',
    overrideMessage,
    // `Component` covers `const { Component } = Shopware` and an undeclared `Component` global alike.
    globals: Object.entries(functions).flatMap(([name, message]) => [
        { path: `Shopware.Component.${name}`, message },
        { path: `Component.${name}`, message },
    ]),
    imports: [
        {
            source: /^src\/app\/adapter\/composition-extension-system(\/|$)/,
            message:
                'src/app/adapter/composition-extension-system is private in 6.8. Write native setup SFCs instead: ' +
                '<name>.vue with swDefinePublic({ … }) or <component>.override.vue with swDefineOverride({ … }).',
        },
    ],
};
