/**
 * @sw-package framework
 *
 * @experimental stableVersion:v6.9.0 feature:ADMIN_MIXIN_COMPOSABLES
 */

/**
 * The Administration's own `useRoute` from vue-router, the composable twin of `$route`.
 *
 * Published so an extension's `<script setup>` reaches the app's router. A copy of vue-router bundled
 * into an extension looks up a different injection key and returns `undefined`.
 *
 *     import { useRoute } from 'shopware:composables';
 *
 *     const route = useRoute();
 *     const productId = computed(() => route.params.id);
 *
 * @private
 */
export { useRoute as default } from 'vue-router';
