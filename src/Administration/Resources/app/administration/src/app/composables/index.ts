/**
 * @sw-package framework
 *
 * @experimental stableVersion:v6.9.0 feature:ADMIN_MIXIN_COMPOSABLES
 *
 * The composables published as `Shopware.Composables` and `shopware:composables`: every composable that
 * replaces a mixin, plus the app's own vue-i18n `useI18n` and the vue-router composables grouped in `router`.
 * The generator reads this literal, so it has to stay a plain `export default { … }`.
 */
import router from './router';
import useCmsElement from './use-cms-element';
import useCmsState from './use-cms-state';
import useI18n from './use-i18n';
import useInlineSnippet from './use-inline-snippet';
import useListing from './use-listing';
import useMediaGridListener from './use-media-grid-listener';
import useMediaSidebarModal from './use-media-sidebar-modal';
import useNotification from './use-notification';
import useNotificationTranslation from './use-notification-translation';
import usePlaceholder from './use-placeholder';
import usePosition from './use-position';
import useRuleBetweenOperator from './use-rule-between-operator';
import useRuleContainer from './use-rule-container';
import useSalutation from './use-salutation';
import useTranslateWithFallback from './use-translate-with-fallback';
import useUserSettings from './use-user-settings';
import useValidation from './use-validation';
import useVideoCover from './use-video-cover';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    router,
    useCmsElement,
    useCmsState,
    useI18n,
    useInlineSnippet,
    useListing,
    useMediaGridListener,
    useMediaSidebarModal,
    useNotification,
    useNotificationTranslation,
    usePlaceholder,
    usePosition,
    useRuleBetweenOperator,
    useRuleContainer,
    useSalutation,
    useTranslateWithFallback,
    useUserSettings,
    useValidation,
    useVideoCover,
};
