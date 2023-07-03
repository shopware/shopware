/**
 * @package admin
 *
 * This is the initial start file for the whole administration. It loads
 * the Shopware Core with the Shopware object. And then starts to execute
 * the application.
 */
// @ts-expect-error - we use an webpack alias for compat mode
import { configureCompat } from 'vue';
import 'src/core/shopware';
import 'src/app/main';

/* eslint-disable max-len */
// @ts-expect-error - we know this exists
// eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
if (window._features_?.vue3) {
    // eslint-disable-next-line @typescript-eslint/no-unsafe-call
    configureCompat({
        GLOBAL_MOUNT: true, // https://v3-migration.vuejs.org/breaking-changes/global-api.html#mounting-app-instance
        GLOBAL_EXTEND: true, // https://v3-migration.vuejs.org/breaking-changes/global-api.html#vue-extend-replaced-by-definecomponent
        GLOBAL_PROTOTYPE: true, // https://v3-migration.vuejs.org/breaking-changes/global-api.html#vue-prototype-replaced-by-config-globalproperties
        GLOBAL_SET: true,
        GLOBAL_DELETE: true,
        GLOBAL_OBSERVABLE: true, // https://vuejs.org/api/reactivity-core.html#reactive
        CONFIG_KEY_CODES: true, // https://v3-migration.vuejs.org/breaking-changes/keycode-modifiers.html
        CONFIG_WHITESPACE: true,
        INSTANCE_SET: true,
        INSTANCE_DELETE: true,
        INSTANCE_EVENT_EMITTER: true, // https://v3-migration.vuejs.org/breaking-changes/events-api.html
        INSTANCE_EVENT_HOOKS: true, // https://v3-migration.vuejs.org/breaking-changes/vnode-lifecycle-events.html
        INSTANCE_CHILDREN: true, // https://v3-migration.vuejs.org/breaking-changes/children.html
        INSTANCE_LISTENERS: true, // https://v3-migration.vuejs.org/breaking-changes/listeners-removed.html
        INSTANCE_SCOPED_SLOTS: true, // https://v3-migration.vuejs.org/breaking-changes/slots-unification.html
        INSTANCE_ATTRS_CLASS_STYLE: true, // https://v3-migration.vuejs.org/breaking-changes/attrs-includes-class-style.html
        OPTIONS_DATA_FN: true, // https://v3-migration.vuejs.org/breaking-changes/data-option.html
        OPTIONS_DATA_MERGE: true, // https://v3-migration.vuejs.org/breaking-changes/data-option.html
        OPTIONS_BEFORE_DESTROY: true,
        OPTIONS_DESTROYED: true,
        WATCH_ARRAY: true, // https://v3-migration.vuejs.org/breaking-changes/watch.html
        V_ON_KEYCODE_MODIFIER: true, // https://v3-migration.vuejs.org/breaking-changes/keycode-modifiers.html
        CUSTOM_DIR: true, // https://v3-migration.vuejs.org/breaking-changes/custom-directives.html
        ATTR_FALSE_VALUE: true, // https://v3-migration.vuejs.org/breaking-changes/attribute-coercion.html
        ATTR_ENUMERATED_COERCION: true, // https://v3-migration.vuejs.org/breaking-changes/attribute-coercion.html
        TRANSITION_GROUP_ROOT: true, // https://v3-migration.vuejs.org/breaking-changes/transition-group.html
        COMPONENT_ASYNC: true, // https://v3-migration.vuejs.org/breaking-changes/async-components.html
        COMPONENT_FUNCTIONAL: true, // https://v3-migration.vuejs.org/breaking-changes/functional-components.html
        COMPONENT_V_MODEL: true, // https://v3-migration.vuejs.org/breaking-changes/v-model.html
        RENDER_FUNCTION: true, // https://v3-migration.vuejs.org/breaking-changes/render-function-api.html
        FILTERS: true, // https://v3-migration.vuejs.org/breaking-changes/filters.html
        COMPILER_IS_ON_ELEMENT: true, // https://v3-migration.vuejs.org/breaking-changes/custom-elements-interop.html
        COMPILER_V_BIND_SYNC: true, // https://v3-migration.vuejs.org/breaking-changes/v-model.html
        COMPILER_V_BIND_PROP: true,
        COMPILER_V_BIND_OBJECT_ORDER: true, // https://v3-migration.vuejs.org/breaking-changes/v-bind.html
        COMPILER_V_ON_NATIVE: true, // https://v3-migration.vuejs.org/breaking-changes/v-on-native-modifier-removed.html
        COMPILER_V_FOR_REF: true,
        COMPILER_NATIVE_TEMPLATE: true,
        COMPILER_FILTERS: true,
    });
}
/* eslint-enable max-len */
