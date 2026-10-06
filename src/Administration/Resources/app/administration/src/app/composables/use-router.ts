/**
 * @sw-package framework
 *
 * @experimental stableVersion:v6.9.0 feature:ADMIN_MIXIN_COMPOSABLES
 */

/**
 * The Administration's own `useRouter` from vue-router, the composable twin of `$router`.
 *
 * Published so an extension's `<script setup>` reaches the app's router. A copy of vue-router bundled
 * into an extension looks up a different injection key and returns `undefined`.
 *
 *     import { useRouter } from 'shopware:composables';
 *
 *     const router = useRouter();
 *     void router.push({ name: 'sw.product.index' });
 *
 * @private
 */
export { useRouter as default } from 'vue-router';
