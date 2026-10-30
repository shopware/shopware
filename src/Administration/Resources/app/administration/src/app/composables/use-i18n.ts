/**
 * @sw-package framework
 *
 * @experimental stableVersion:v6.9.0 feature:ADMIN_MIXIN_COMPOSABLES
 */

/**
 * The Administration's own `useI18n` from vue-i18n, the composable twin of `$t`/`$tc`/`$te`.
 *
 * Published so an extension's `<script setup>` reaches the app's i18n instance. A copy of vue-i18n
 * bundled into an extension looks up a different injection key and finds no i18n at all.
 *
 * Called without options, it returns the global composer, so `t()` sees every registered snippet and
 * falls back to `Shopware.Context.app.fallbackLocale` on its own.
 *
 *     import { useI18n } from 'shopware:composables';
 *
 *     const { t } = useI18n();
 *     const hint = computed(() => t('swag-example.hint', { count: count.value }));
 *
 * @private
 */
export { useI18n as default } from 'vue-i18n';
