/**
 * @sw-package framework
 *
 * @experimental stableVersion:v6.9.0 feature:ADMIN_MIXIN_COMPOSABLES
 */
import { onBeforeRouteLeave, onBeforeRouteUpdate, useRoute, useRouter } from 'vue-router';

/**
 * The Administration's own vue-router composables, the composable twins of `$route`, `$router` and the
 * `beforeRouteLeave`/`beforeRouteUpdate` options, published as `shopware:composables/router`.
 *
 * Each of them injects the app's router under a key private to one copy of vue-router. A copy bundled
 * into an extension looks up a different key, so `useRoute()` returns `undefined` and a route guard is
 * never registered.
 *
 *     import { useRoute, onBeforeRouteLeave } from 'shopware:composables/router';
 *
 *     const route = useRoute();
 *     const productId = computed(() => route.params.id);
 *     onBeforeRouteLeave(() => !isDirty.value);
 *
 * @private
 */
export default {
    useRoute,
    useRouter,
    onBeforeRouteLeave,
    onBeforeRouteUpdate,
};
