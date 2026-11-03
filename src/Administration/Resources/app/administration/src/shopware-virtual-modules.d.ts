/**
 * @sw-package framework
 *
 * @experimental stableVersion:v6.8.0
 *
 * Types for the `shopware:*` modules, which expose the global `Shopware` object as ordinary
 * imports. `build/vite-plugins/virtual-shopware-modules` generates their runtime counterpart from
 * the same `shopware-modules.json`.
 *
 * Generated. Run `composer admin:generate-shopware-modules` after adding a utility, DAL class, composable, mixin, store,
 * or mini-module, or after changing the exports of a mini-module's index.ts.
 */

/* eslint-disable sw-deprecation-rules/private-feature-declarations -- Intentional public facade. */

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:utils' {
    import type branch from 'src/core/service/util.service';

    const members: typeof branch;

    export default members;
    export const createId: (typeof members)['createId'];
    export const throttle: (typeof members)['throttle'];
    export const debounce: (typeof members)['debounce'];
    export const flow: (typeof members)['flow'];
    export const get: (typeof members)['get'];
    export const object: (typeof members)['object'];
    export const debug: (typeof members)['debug'];
    export const format: (typeof members)['format'];
    export const dom: (typeof members)['dom'];
    export const string: (typeof members)['string'];
    export const types: (typeof members)['types'];
    export const fileReader: (typeof members)['fileReader'];
    export const sort: (typeof members)['sort'];
    export const array: (typeof members)['array'];
    export const moveItem: (typeof members)['moveItem'];
    export const VueHelper: (typeof members)['VueHelper'];
    export const EventBus: (typeof members)['EventBus'];
    export const genericRuleCondition: (typeof members)['genericRuleCondition'];
    export const unitConversion: (typeof members)['unitConversion'];
    export const extension: (typeof members)['extension'];
    export const mapInheritanceSlotPropsToMeteorProps: (typeof members)['mapInheritanceSlotPropsToMeteorProps'];
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:utils/createId' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['createId'];

    export default member;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:utils/throttle' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['throttle'];

    export default member;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:utils/debounce' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['debounce'];

    export default member;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:utils/flow' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['flow'];

    export default member;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:utils/get' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['get'];

    export default member;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:utils/object' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['object'];

    export default member;
    export const deepCopyObject: (typeof member)['deepCopyObject'];
    export const hasOwnProperty: (typeof member)['hasOwnProperty'];
    export const getObjectDiff: (typeof member)['getObjectDiff'];
    export const getArrayChanges: (typeof member)['getArrayChanges'];
    export const cloneDeep: (typeof member)['cloneDeep'];
    export const merge: (typeof member)['merge'];
    export const mergeWith: (typeof member)['mergeWith'];
    export const deepMergeObject: (typeof member)['deepMergeObject'];
    export const get: (typeof member)['get'];
    export const set: (typeof member)['set'];
    export const pick: (typeof member)['pick'];
    export const unset: (typeof member)['unset'];
    export const has: (typeof member)['has'];
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:utils/debug' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['debug'];

    export default member;
    export const warn: (typeof member)['warn'];
    export const error: (typeof member)['error'];
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:utils/format' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['format'];

    export default member;
    export const currency: (typeof member)['currency'];
    export const date: (typeof member)['date'];
    export const dateWithUserTimezone: (typeof member)['dateWithUserTimezone'];
    export const fileSize: (typeof member)['fileSize'];
    export const localeName: (typeof member)['localeName'];
    export const md5: (typeof member)['md5'];
    export const toISODate: (typeof member)['toISODate'];
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:utils/dom' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['dom'];

    export default member;
    export const getScrollbarHeight: (typeof member)['getScrollbarHeight'];
    export const getScrollbarWidth: (typeof member)['getScrollbarWidth'];
    export const copyStringToClipboard: (typeof member)['copyStringToClipboard'];
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:utils/string' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['string'];

    export default member;
    export const capitalizeString: (typeof member)['capitalizeString'];
    export const camelCase: (typeof member)['camelCase'];
    export const upperFirst: (typeof member)['upperFirst'];
    export const kebabCase: (typeof member)['kebabCase'];
    export const snakeCase: (typeof member)['snakeCase'];
    export const md5: (typeof member)['md5'];
    export const isEmptyOrSpaces: (typeof member)['isEmptyOrSpaces'];
    export const isUrl: (typeof member)['isUrl'];
    export const isValidIp: (typeof member)['isValidIp'];
    export const isValidCidr: (typeof member)['isValidCidr'];
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:utils/types' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['types'];

    export default member;
    export const isObject: (typeof member)['isObject'];
    export const isPlainObject: (typeof member)['isPlainObject'];
    export const isEmpty: (typeof member)['isEmpty'];
    export const isRegExp: (typeof member)['isRegExp'];
    export const isArray: (typeof member)['isArray'];
    export const isFunction: (typeof member)['isFunction'];
    export const isDate: (typeof member)['isDate'];
    export const isString: (typeof member)['isString'];
    export const isBoolean: (typeof member)['isBoolean'];
    export const isEqual: (typeof member)['isEqual'];
    export const isNumber: (typeof member)['isNumber'];
    export const isUndefined: (typeof member)['isUndefined'];
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:utils/fileReader' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['fileReader'];

    export default member;
    export const readAsArrayBuffer: (typeof member)['readAsArrayBuffer'];
    export const readAsDataURL: (typeof member)['readAsDataURL'];
    export const readAsText: (typeof member)['readAsText'];
    export const getNameAndExtensionFromFile: (typeof member)['getNameAndExtensionFromFile'];
    export const getNameAndExtensionFromUrl: (typeof member)['getNameAndExtensionFromUrl'];
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:utils/sort' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['sort'];

    export default member;
    export const afterSort: (typeof member)['afterSort'];
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:utils/array' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['array'];

    export default member;
    export const flattenDeep: (typeof member)['flattenDeep'];
    export const remove: (typeof member)['remove'];
    export const slice: (typeof member)['slice'];
    export const uniqBy: (typeof member)['uniqBy'];
    export const chunk: (typeof member)['chunk'];
    export const intersectionBy: (typeof member)['intersectionBy'];
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:utils/moveItem' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['moveItem'];

    export default member;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:utils/VueHelper' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['VueHelper'];

    export default member;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:utils/EventBus' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['EventBus'];

    export default member;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:utils/genericRuleCondition' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['genericRuleCondition'];

    export default member;
    export const getPlaceholderSnippet: (typeof member)['getPlaceholderSnippet'];
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:utils/unitConversion' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['unitConversion'];

    export default member;
    export const convert: (typeof member)['convert'];
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:utils/extension' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['extension'];

    export default member;
    export const getExtensionNameByOrigin: (typeof member)['getExtensionNameByOrigin'];
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:utils/mapInheritanceSlotPropsToMeteorProps' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['mapInheritanceSlotPropsToMeteorProps'];

    export default member;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:data' {
    import type branch from 'src/core/data/index';

    const members: typeof branch;

    export default members;
    export const ChangesetGenerator: (typeof members)['ChangesetGenerator'];
    export const Criteria: (typeof members)['Criteria'];
    export const Entity: (typeof members)['Entity'];
    export const EntityCollection: (typeof members)['EntityCollection'];
    export const EntityDefinition: (typeof members)['EntityDefinition'];
    export const EntityFactory: (typeof members)['EntityFactory'];
    export const EntityHydrator: (typeof members)['EntityHydrator'];
    export const Repository: (typeof members)['Repository'];
    export const ErrorResolver: (typeof members)['ErrorResolver'];
    export const FilterFactory: (typeof members)['FilterFactory'];
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:data/ChangesetGenerator' {
    import type branch from 'src/core/data/index';

    const member: (typeof branch)['ChangesetGenerator'];

    export default member;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:data/Criteria' {
    import type branch from 'src/core/data/index';

    const member: (typeof branch)['Criteria'];

    export default member;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:data/Entity' {
    import type branch from 'src/core/data/index';

    const member: (typeof branch)['Entity'];

    export default member;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:data/EntityCollection' {
    import type branch from 'src/core/data/index';

    const member: (typeof branch)['EntityCollection'];

    export default member;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:data/EntityDefinition' {
    import type branch from 'src/core/data/index';

    const member: (typeof branch)['EntityDefinition'];

    export default member;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:data/EntityFactory' {
    import type branch from 'src/core/data/index';

    const member: (typeof branch)['EntityFactory'];

    export default member;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:data/EntityHydrator' {
    import type branch from 'src/core/data/index';

    const member: (typeof branch)['EntityHydrator'];

    export default member;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:data/Repository' {
    import type branch from 'src/core/data/index';

    const member: (typeof branch)['Repository'];

    export default member;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:data/ErrorResolver' {
    import type branch from 'src/core/data/index';

    const member: (typeof branch)['ErrorResolver'];

    export default member;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:data/FilterFactory' {
    import type branch from 'src/core/data/index';

    const member: (typeof branch)['FilterFactory'];

    export default member;
}

/** @experimental stableVersion:v6.9.0 feature:ADMIN_MIXIN_COMPOSABLES */
declare module 'shopware:composables' {
    import type branch from 'src/app/composables/index';

    const members: typeof branch;

    export default members;
    export const useCmsElement: (typeof members)['useCmsElement'];
    export const useCmsState: (typeof members)['useCmsState'];
    export const useInlineSnippet: (typeof members)['useInlineSnippet'];
    export const useListing: (typeof members)['useListing'];
    export const useMediaGridListener: (typeof members)['useMediaGridListener'];
    export const useMediaSidebarModal: (typeof members)['useMediaSidebarModal'];
    export const useNotification: (typeof members)['useNotification'];
    export const useNotificationTranslation: (typeof members)['useNotificationTranslation'];
    export const usePlaceholder: (typeof members)['usePlaceholder'];
    export const usePosition: (typeof members)['usePosition'];
    export const useRuleBetweenOperator: (typeof members)['useRuleBetweenOperator'];
    export const useRuleContainer: (typeof members)['useRuleContainer'];
    export const useSalutation: (typeof members)['useSalutation'];
    export const useTranslateWithFallback: (typeof members)['useTranslateWithFallback'];
    export const useUserSettings: (typeof members)['useUserSettings'];
    export const useValidation: (typeof members)['useValidation'];
    export const useVideoCover: (typeof members)['useVideoCover'];
}

/** @experimental stableVersion:v6.9.0 feature:ADMIN_MIXIN_COMPOSABLES */
declare module 'shopware:composables/useCmsElement' {
    import type branch from 'src/app/composables/index';

    const member: (typeof branch)['useCmsElement'];

    export default member;
}

/** @experimental stableVersion:v6.9.0 feature:ADMIN_MIXIN_COMPOSABLES */
declare module 'shopware:composables/useCmsState' {
    import type branch from 'src/app/composables/index';

    const member: (typeof branch)['useCmsState'];

    export default member;
}

/** @experimental stableVersion:v6.9.0 feature:ADMIN_MIXIN_COMPOSABLES */
declare module 'shopware:composables/useInlineSnippet' {
    import type branch from 'src/app/composables/index';

    const member: (typeof branch)['useInlineSnippet'];

    export default member;
}

/** @experimental stableVersion:v6.9.0 feature:ADMIN_MIXIN_COMPOSABLES */
declare module 'shopware:composables/useListing' {
    import type branch from 'src/app/composables/index';

    const member: (typeof branch)['useListing'];

    export default member;
}

/** @experimental stableVersion:v6.9.0 feature:ADMIN_MIXIN_COMPOSABLES */
declare module 'shopware:composables/useMediaGridListener' {
    import type branch from 'src/app/composables/index';

    const member: (typeof branch)['useMediaGridListener'];

    export default member;
}

/** @experimental stableVersion:v6.9.0 feature:ADMIN_MIXIN_COMPOSABLES */
declare module 'shopware:composables/useMediaSidebarModal' {
    import type branch from 'src/app/composables/index';

    const member: (typeof branch)['useMediaSidebarModal'];

    export default member;
}

/** @experimental stableVersion:v6.9.0 feature:ADMIN_MIXIN_COMPOSABLES */
declare module 'shopware:composables/useNotification' {
    import type branch from 'src/app/composables/index';

    const member: (typeof branch)['useNotification'];

    export default member;
}

/** @experimental stableVersion:v6.9.0 feature:ADMIN_MIXIN_COMPOSABLES */
declare module 'shopware:composables/useNotificationTranslation' {
    import type branch from 'src/app/composables/index';

    const member: (typeof branch)['useNotificationTranslation'];

    export default member;
}

/** @experimental stableVersion:v6.9.0 feature:ADMIN_MIXIN_COMPOSABLES */
declare module 'shopware:composables/usePlaceholder' {
    import type branch from 'src/app/composables/index';

    const member: (typeof branch)['usePlaceholder'];

    export default member;
}

/** @experimental stableVersion:v6.9.0 feature:ADMIN_MIXIN_COMPOSABLES */
declare module 'shopware:composables/usePosition' {
    import type branch from 'src/app/composables/index';

    const member: (typeof branch)['usePosition'];

    export default member;
}

/** @experimental stableVersion:v6.9.0 feature:ADMIN_MIXIN_COMPOSABLES */
declare module 'shopware:composables/useRuleBetweenOperator' {
    import type branch from 'src/app/composables/index';

    const member: (typeof branch)['useRuleBetweenOperator'];

    export default member;
}

/** @experimental stableVersion:v6.9.0 feature:ADMIN_MIXIN_COMPOSABLES */
declare module 'shopware:composables/useRuleContainer' {
    import type branch from 'src/app/composables/index';

    const member: (typeof branch)['useRuleContainer'];

    export default member;
}

/** @experimental stableVersion:v6.9.0 feature:ADMIN_MIXIN_COMPOSABLES */
declare module 'shopware:composables/useSalutation' {
    import type branch from 'src/app/composables/index';

    const member: (typeof branch)['useSalutation'];

    export default member;
}

/** @experimental stableVersion:v6.9.0 feature:ADMIN_MIXIN_COMPOSABLES */
declare module 'shopware:composables/useTranslateWithFallback' {
    import type branch from 'src/app/composables/index';

    const member: (typeof branch)['useTranslateWithFallback'];

    export default member;
}

/** @experimental stableVersion:v6.9.0 feature:ADMIN_MIXIN_COMPOSABLES */
declare module 'shopware:composables/useUserSettings' {
    import type branch from 'src/app/composables/index';

    const member: (typeof branch)['useUserSettings'];

    export default member;
}

/** @experimental stableVersion:v6.9.0 feature:ADMIN_MIXIN_COMPOSABLES */
declare module 'shopware:composables/useValidation' {
    import type branch from 'src/app/composables/index';

    const member: (typeof branch)['useValidation'];

    export default member;
}

/** @experimental stableVersion:v6.9.0 feature:ADMIN_MIXIN_COMPOSABLES */
declare module 'shopware:composables/useVideoCover' {
    import type branch from 'src/app/composables/index';

    const member: (typeof branch)['useVideoCover'];

    export default member;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:mixins/notification' {
    const mixin: MixinContainer['notification'];

    export default mixin;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:mixins/validation' {
    const mixin: MixinContainer['validation'];

    export default mixin;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:mixins/user-settings' {
    const mixin: MixinContainer['user-settings'];

    export default mixin;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:mixins/sw-inline-snippet' {
    const mixin: MixinContainer['sw-inline-snippet'];

    export default mixin;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:mixins/translate-with-fallback' {
    const mixin: MixinContainer['translate-with-fallback'];

    export default mixin;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:mixins/notification-translation' {
    const mixin: MixinContainer['notification-translation'];

    export default mixin;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:mixins/salutation' {
    const mixin: MixinContainer['salutation'];

    export default mixin;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:mixins/ruleContainer' {
    const mixin: MixinContainer['ruleContainer'];

    export default mixin;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:mixins/remove-api-error' {
    const mixin: MixinContainer['remove-api-error'];

    export default mixin;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:mixins/position' {
    const mixin: MixinContainer['position'];

    export default mixin;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:mixins/placeholder' {
    const mixin: MixinContainer['placeholder'];

    export default mixin;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:mixins/listing' {
    const mixin: MixinContainer['listing'];

    export default mixin;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:mixins/generic-condition' {
    const mixin: MixinContainer['generic-condition'];

    export default mixin;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:mixins/sw-form-field' {
    const mixin: MixinContainer['sw-form-field'];

    export default mixin;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:mixins/discard-detail-page-changes' {
    const mixin: MixinContainer['discard-detail-page-changes'];

    export default mixin;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:mixins/rule-between-operator' {
    const mixin: MixinContainer['rule-between-operator'];

    export default mixin;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:stores/cmsPage' {
    const useStore: () => PiniaRootState['cmsPage'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:stores/topBarButton' {
    const useStore: () => PiniaRootState['topBarButton'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:stores/teaserPopover' {
    const useStore: () => PiniaRootState['teaserPopover'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:stores/adminMenu' {
    const useStore: () => PiniaRootState['adminMenu'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:stores/inAppPurchaseCheckout' {
    const useStore: () => PiniaRootState['inAppPurchaseCheckout'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:stores/extensionComponentSections' {
    const useStore: () => PiniaRootState['extensionComponentSections'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:stores/blockOverride' {
    const useStore: () => PiniaRootState['blockOverride'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:stores/extensionEntryRoutes' {
    const useStore: () => PiniaRootState['extensionEntryRoutes'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:stores/extensionSdkModules' {
    const useStore: () => PiniaRootState['extensionSdkModules'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:stores/extensions' {
    const useStore: () => PiniaRootState['extensions'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:stores/error' {
    const useStore: () => PiniaRootState['error'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:stores/context' {
    const useStore: () => PiniaRootState['context'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:stores/adminHelpCenter' {
    const useStore: () => PiniaRootState['adminHelpCenter'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:stores/actionButtons' {
    const useStore: () => PiniaRootState['actionButtons'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:stores/licenseViolation' {
    const useStore: () => PiniaRootState['licenseViolation'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:stores/extensionMainModules' {
    const useStore: () => PiniaRootState['extensionMainModules'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:stores/marketing' {
    const useStore: () => PiniaRootState['marketing'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:stores/sdkLocation' {
    const useStore: () => PiniaRootState['sdkLocation'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:stores/ruleConditionsConfig' {
    const useStore: () => PiniaRootState['ruleConditionsConfig'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:stores/settingsItems' {
    const useStore: () => PiniaRootState['settingsItems'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:stores/shopwareApps' {
    const useStore: () => PiniaRootState['shopwareApps'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:stores/system' {
    const useStore: () => PiniaRootState['system'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:stores/modals' {
    const useStore: () => PiniaRootState['modals'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:stores/sidebar' {
    const useStore: () => PiniaRootState['sidebar'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:stores/menuItem' {
    const useStore: () => PiniaRootState['menuItem'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:stores/notification' {
    const useStore: () => PiniaRootState['notification'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:stores/tabs' {
    const useStore: () => PiniaRootState['tabs'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:stores/session' {
    const useStore: () => PiniaRootState['session'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:stores/swCategoryDetail' {
    const useStore: () => PiniaRootState['swCategoryDetail'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:stores/swSeoUrl' {
    const useStore: () => PiniaRootState['swSeoUrl'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:stores/shopwareExtensions' {
    const useStore: () => PiniaRootState['shopwareExtensions'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:stores/swOrderDetail' {
    const useStore: () => PiniaRootState['swOrderDetail'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:stores/swOrder' {
    const useStore: () => PiniaRootState['swOrder'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:stores/swShippingDetail' {
    const useStore: () => PiniaRootState['swShippingDetail'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:stores/paymentOverviewCard' {
    const useStore: () => PiniaRootState['paymentOverviewCard'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:stores/swProductDetail' {
    const useStore: () => PiniaRootState['swProductDetail'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:stores/swProfile' {
    const useStore: () => PiniaRootState['swProfile'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:stores/swPromotionDetail' {
    const useStore: () => PiniaRootState['swPromotionDetail'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:stores/swFlow' {
    const useStore: () => PiniaRootState['swFlow'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:stores/swBulkEdit' {
    const useStore: () => PiniaRootState['swBulkEdit'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:stores/mediaModal' {
    const useStore: () => PiniaRootState['mediaModal'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-bulk-edit-product-visibility' {
    export { default } from 'src/module/sw-bulk-edit/component/product/sw-bulk-edit-product-visibility';
    export type * from 'src/module/sw-bulk-edit/component/product/sw-bulk-edit-product-visibility';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-condition-base-line-item' {
    export { default } from 'src/app/component/rule/sw-condition-base-line-item';
    export type * from 'src/app/component/rule/sw-condition-base-line-item';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-condition-billing-zip-code' {
    export { default } from 'src/app/component/rule/condition-type/sw-condition-billing-zip-code';
    export type * from 'src/app/component/rule/condition-type/sw-condition-billing-zip-code';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-condition-customer-custom-field' {
    export { default } from 'src/app/component/rule/condition-type/sw-condition-customer-custom-field';
    export type * from 'src/app/component/rule/condition-type/sw-condition-customer-custom-field';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-condition-date-range' {
    export { default } from 'src/app/component/rule/condition-type/sw-condition-date-range';
    export type * from 'src/app/component/rule/condition-type/sw-condition-date-range';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-condition-generic' {
    export { default } from 'src/app/component/rule/condition-type/sw-condition-generic';
    export type * from 'src/app/component/rule/condition-type/sw-condition-generic';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-condition-goods-count' {
    export { default } from 'src/app/component/rule/condition-type/sw-condition-goods-count';
    export type * from 'src/app/component/rule/condition-type/sw-condition-goods-count';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-condition-goods-price' {
    export { default } from 'src/app/component/rule/condition-type/sw-condition-goods-price';
    export type * from 'src/app/component/rule/condition-type/sw-condition-goods-price';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-condition-is-always-valid' {
    export { default } from 'src/app/component/rule/condition-type/sw-condition-is-always-valid';
    export type * from 'src/app/component/rule/condition-type/sw-condition-is-always-valid';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-condition-line-item' {
    export { default } from 'src/app/component/rule/condition-type/sw-condition-line-item';
    export type * from 'src/app/component/rule/condition-type/sw-condition-line-item';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-condition-line-item-custom-field' {
    export { default } from 'src/app/component/rule/condition-type/sw-condition-line-item-custom-field';
    export type * from 'src/app/component/rule/condition-type/sw-condition-line-item-custom-field';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-condition-line-item-goods-total' {
    export { default } from 'src/app/component/rule/condition-type/sw-condition-line-item-goods-total';
    export type * from 'src/app/component/rule/condition-type/sw-condition-line-item-goods-total';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-condition-line-item-in-category' {
    export { default } from 'src/app/component/rule/condition-type/sw-condition-line-item-in-category';
    export type * from 'src/app/component/rule/condition-type/sw-condition-line-item-in-category';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-condition-line-item-property' {
    export { default } from 'src/app/component/rule/condition-type/sw-condition-line-item-property';
    export type * from 'src/app/component/rule/condition-type/sw-condition-line-item-property';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-condition-line-item-purchase-price' {
    export { default } from 'src/app/component/rule/condition-type/sw-condition-line-item-purchase-price';
    export type * from 'src/app/component/rule/condition-type/sw-condition-line-item-purchase-price';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-condition-line-item-with-quantity' {
    export { default } from 'src/app/component/rule/condition-type/sw-condition-line-item-with-quantity';
    export type * from 'src/app/component/rule/condition-type/sw-condition-line-item-with-quantity';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-condition-not-found' {
    export { default } from 'src/app/component/rule/condition-type/sw-condition-not-found';
    export type * from 'src/app/component/rule/condition-type/sw-condition-not-found';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-condition-order-custom-field' {
    export { default } from 'src/app/component/rule/condition-type/sw-condition-order-custom-field';
    export type * from 'src/app/component/rule/condition-type/sw-condition-order-custom-field';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-condition-script' {
    export { default } from 'src/app/component/rule/condition-type/sw-condition-script';
    export type * from 'src/app/component/rule/condition-type/sw-condition-script';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-condition-shipping-zip-code' {
    export { default } from 'src/app/component/rule/condition-type/sw-condition-shipping-zip-code';
    export type * from 'src/app/component/rule/condition-type/sw-condition-shipping-zip-code';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-country-state-detail' {
    export { default } from 'src/module/sw-settings-country/component/sw-country-state-detail';
    export type * from 'src/module/sw-settings-country/component/sw-country-state-detail';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-custom-field-detail' {
    export { default } from 'src/module/sw-settings-custom-field/component/sw-custom-field-detail';
    export type * from 'src/module/sw-settings-custom-field/component/sw-custom-field-detail';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-custom-field-list' {
    export { default } from 'src/module/sw-settings-custom-field/component/sw-custom-field-list';
    export type * from 'src/module/sw-settings-custom-field/component/sw-custom-field-list';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-custom-field-set-detail-base' {
    export { default } from 'src/module/sw-settings-custom-field/component/sw-custom-field-set-detail-base';
    export type * from 'src/module/sw-settings-custom-field/component/sw-custom-field-set-detail-base';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-custom-field-translated-labels' {
    export { default } from 'src/module/sw-settings-custom-field/component/sw-custom-field-translated-labels';
    export type * from 'src/module/sw-settings-custom-field/component/sw-custom-field-translated-labels';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-custom-field-type-base' {
    export { default } from 'src/module/sw-settings-custom-field/component/sw-custom-field-type-base';
    export type * from 'src/module/sw-settings-custom-field/component/sw-custom-field-type-base';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-custom-field-type-date' {
    export { default } from 'src/module/sw-settings-custom-field/component/sw-custom-field-type-date';
    export type * from 'src/module/sw-settings-custom-field/component/sw-custom-field-type-date';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-custom-field-type-entity' {
    export { default } from 'src/module/sw-settings-custom-field/component/sw-custom-field-type-entity';
    export type * from 'src/module/sw-settings-custom-field/component/sw-custom-field-type-entity';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-custom-field-type-number' {
    export { default } from 'src/module/sw-settings-custom-field/component/sw-custom-field-type-number';
    export type * from 'src/module/sw-settings-custom-field/component/sw-custom-field-type-number';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-custom-field-type-select' {
    export { default } from 'src/module/sw-settings-custom-field/component/sw-custom-field-type-select';
    export type * from 'src/module/sw-settings-custom-field/component/sw-custom-field-type-select';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-datepicker-deprecated' {
    export { default } from 'src/app/component/form/sw-datepicker-deprecated';
    export type * from 'src/app/component/form/sw-datepicker-deprecated';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-duplicated-media-v2' {
    export { default } from 'src/app/component/utils/sw-duplicated-media-v2';
    export type * from 'src/app/component/utils/sw-duplicated-media-v2';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-image-slider' {
    export { default } from 'src/app/component/media/sw-image-slider';
    export type * from 'src/app/component/media/sw-image-slider';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-import-export-activity' {
    export { default } from 'src/module/sw-import-export/component/sw-import-export-activity';
    export type * from 'src/module/sw-import-export/component/sw-import-export-activity';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-media-base-item' {
    export { default } from 'src/app/component/media/sw-media-base-item';
    export type * from 'src/app/component/media/sw-media-base-item';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-media-collapse' {
    export { default } from 'src/module/sw-media/component/sw-media-collapse';
    export type * from 'src/module/sw-media/component/sw-media-collapse';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-media-compact-upload-v2' {
    export { default } from 'src/app/component/media/sw-media-compact-upload-v2';
    export type * from 'src/app/component/media/sw-media-compact-upload-v2';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-media-field' {
    export { default } from 'src/app/component/media/sw-media-field';
    export type * from 'src/app/component/media/sw-media-field';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-media-folder-content' {
    export { default } from 'src/app/component/media/sw-media-folder-content';
    export type * from 'src/app/component/media/sw-media-folder-content';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-media-folder-item' {
    export { default } from 'src/app/component/media/sw-media-folder-item';
    export type * from 'src/app/component/media/sw-media-folder-item';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-media-list-selection-v2' {
    export { default } from 'src/app/component/media/sw-media-list-selection-v2';
    export type * from 'src/app/component/media/sw-media-list-selection-v2';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-media-modal-delete' {
    export { default } from 'src/app/component/media/sw-media-modal-delete';
    export type * from 'src/app/component/media/sw-media-modal-delete';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-media-modal-folder-dissolve' {
    export { default } from 'src/app/component/media/sw-media-modal-folder-dissolve';
    export type * from 'src/app/component/media/sw-media-modal-folder-dissolve';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-media-modal-move' {
    export { default } from 'src/app/component/media/sw-media-modal-move';
    export type * from 'src/app/component/media/sw-media-modal-move';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-media-modal-replace' {
    export { default } from 'src/app/component/media/sw-media-modal-replace';
    export type * from 'src/app/component/media/sw-media-modal-replace';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-media-preview-v2' {
    export { default } from 'src/app/component/media/sw-media-preview-v2';
    export type * from 'src/app/component/media/sw-media-preview-v2';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-media-upload-v2' {
    export { default } from 'src/app/component/media/sw-media-upload-v2';
    export type * from 'src/app/component/media/sw-media-upload-v2';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-media-url-form' {
    export { default } from 'src/app/component/media/sw-media-url-form';
    export type * from 'src/app/component/media/sw-media-url-form';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-model-editor' {
    export { default } from 'src/app/component/media/sw-model-editor';
    export type * from 'src/app/component/media/sw-model-editor';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-model-viewer' {
    export { default } from 'src/app/component/media/sw-model-viewer';
    export type * from 'src/app/component/media/sw-model-viewer';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-payment-card' {
    export { default } from 'src/module/sw-settings-payment/component/sw-payment-card';
    export type * from 'src/module/sw-settings-payment/component/sw-payment-card';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-plugin-box' {
    export { default } from 'src/module/sw-settings-payment/component/sw-plugin-box';
    export type * from 'src/module/sw-settings-payment/component/sw-plugin-box';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-price-rule-modal' {
    export { default } from 'src/module/sw-settings-shipping/component/sw-price-rule-modal';
    export type * from 'src/module/sw-settings-shipping/component/sw-price-rule-modal';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-seo-main-category' {
    export { default } from 'src/module/sw-settings-seo/component/sw-seo-main-category';
    export type * from 'src/module/sw-settings-seo/component/sw-seo-main-category';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-seo-url-template-card' {
    export { default } from 'src/module/sw-settings-seo/component/sw-seo-url-template-card';
    export type * from 'src/module/sw-settings-seo/component/sw-seo-url-template-card';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-basic-information' {
    export { default } from 'src/module/sw-settings-basic-information/page/sw-settings-basic-information';
    export type * from 'src/module/sw-settings-basic-information/page/sw-settings-basic-information';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-cache-index' {
    export { default } from 'src/module/sw-settings-cache/page/sw-settings-cache-index';
    export type * from 'src/module/sw-settings-cache/page/sw-settings-cache-index';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-captcha-select-v2' {
    export { default } from 'src/module/sw-settings-basic-information/component/sw-settings-captcha-select-v2';
    export type * from 'src/module/sw-settings-basic-information/component/sw-settings-captcha-select-v2';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-cart' {
    export { default } from 'src/module/sw-settings-cart/page/sw-settings-cart';
    export type * from 'src/module/sw-settings-cart/page/sw-settings-cart';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-country-create' {
    export { default } from 'src/module/sw-settings-country/page/sw-settings-country-create';
    export type * from 'src/module/sw-settings-country/page/sw-settings-country-create';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-country-currency-dependent-modal' {
    export { default } from 'src/module/sw-settings-country/component/sw-settings-country-currency-dependent-modal';
    export type * from 'src/module/sw-settings-country/component/sw-settings-country-currency-dependent-modal';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-country-currency-hamburger-menu' {
    export { default } from 'src/module/sw-settings-country/component/sw-settings-country-currency-hamburger-menu';
    export type * from 'src/module/sw-settings-country/component/sw-settings-country-currency-hamburger-menu';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-country-detail' {
    export { default } from 'src/module/sw-settings-country/page/sw-settings-country-detail';
    export type * from 'src/module/sw-settings-country/page/sw-settings-country-detail';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-country-general' {
    export { default } from 'src/module/sw-settings-country/component/sw-settings-country-general';
    export type * from 'src/module/sw-settings-country/component/sw-settings-country-general';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-country-list' {
    export { default } from 'src/module/sw-settings-country/page/sw-settings-country-list';
    export type * from 'src/module/sw-settings-country/page/sw-settings-country-list';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-country-state' {
    export { default } from 'src/module/sw-settings-country/component/sw-settings-country-state';
    export type * from 'src/module/sw-settings-country/component/sw-settings-country-state';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-currency-country-modal' {
    export { default } from 'src/module/sw-settings-currency/component/sw-settings-currency-country-modal';
    export type * from 'src/module/sw-settings-currency/component/sw-settings-currency-country-modal';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-currency-detail' {
    export { default } from 'src/module/sw-settings-currency/page/sw-settings-currency-detail';
    export type * from 'src/module/sw-settings-currency/page/sw-settings-currency-detail';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-currency-list' {
    export { default } from 'src/module/sw-settings-currency/page/sw-settings-currency-list';
    export type * from 'src/module/sw-settings-currency/page/sw-settings-currency-list';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-custom-field-set-create' {
    export { default } from 'src/module/sw-settings-custom-field/page/sw-settings-custom-field-set-create';
    export type * from 'src/module/sw-settings-custom-field/page/sw-settings-custom-field-set-create';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-custom-field-set-detail' {
    export { default } from 'src/module/sw-settings-custom-field/page/sw-settings-custom-field-set-detail';
    export type * from 'src/module/sw-settings-custom-field/page/sw-settings-custom-field-set-detail';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-customer-group-detail' {
    export { default } from 'src/module/sw-settings-customer-group/page/sw-settings-customer-group-detail';
    export type * from 'src/module/sw-settings-customer-group/page/sw-settings-customer-group-detail';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-customer-group-list' {
    export { default } from 'src/module/sw-settings-customer-group/page/sw-settings-customer-group-list';
    export type * from 'src/module/sw-settings-customer-group/page/sw-settings-customer-group-list';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-delivery-time-detail' {
    export { default } from 'src/module/sw-settings-delivery-times/page/sw-settings-delivery-time-detail';
    export type * from 'src/module/sw-settings-delivery-times/page/sw-settings-delivery-time-detail';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-delivery-time-list' {
    export { default } from 'src/module/sw-settings-delivery-times/page/sw-settings-delivery-time-list';
    export type * from 'src/module/sw-settings-delivery-times/page/sw-settings-delivery-time-list';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-document-detail' {
    export { default } from 'src/module/sw-settings-document/page/sw-settings-document-detail';
    export type * from 'src/module/sw-settings-document/page/sw-settings-document-detail';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-index' {
    export { default } from 'src/module/sw-settings/page/sw-settings-index';
    export type * from 'src/module/sw-settings/page/sw-settings-index';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-item' {
    export { default } from 'src/module/sw-settings/component/sw-settings-item';
    export type * from 'src/module/sw-settings/component/sw-settings-item';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-language-detail' {
    export { default } from 'src/module/sw-settings-language/page/sw-settings-language-detail';
    export type * from 'src/module/sw-settings-language/page/sw-settings-language-detail';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-listing' {
    export { default } from 'src/module/sw-settings-listing/page/sw-settings-listing';
    export type * from 'src/module/sw-settings-listing/page/sw-settings-listing';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-listing-default-sales-channel' {
    export { default } from 'src/module/sw-settings-listing/component/sw-settings-listing-default-sales-channel';
    export type * from 'src/module/sw-settings-listing/component/sw-settings-listing-default-sales-channel';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-listing-delete-modal' {
    export { default } from 'src/module/sw-settings-listing/component/sw-settings-listing-delete-modal';
    export type * from 'src/module/sw-settings-listing/component/sw-settings-listing-delete-modal';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-listing-option-base' {
    export { default } from 'src/module/sw-settings-listing/page/sw-settings-listing-option-base';
    export type * from 'src/module/sw-settings-listing/page/sw-settings-listing-option-base';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-listing-option-create' {
    export { default } from 'src/module/sw-settings-listing/page/sw-settings-listing-option-create';
    export type * from 'src/module/sw-settings-listing/page/sw-settings-listing-option-create';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-listing-option-criteria-grid' {
    export { default } from 'src/module/sw-settings-listing/component/sw-settings-listing-option-criteria-grid';
    export type * from 'src/module/sw-settings-listing/component/sw-settings-listing-option-criteria-grid';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-listing-option-general-info' {
    export { default } from 'src/module/sw-settings-listing/component/sw-settings-listing-option-general-info';
    export type * from 'src/module/sw-settings-listing/component/sw-settings-listing-option-general-info';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-listing-visibility-detail' {
    export { default } from 'src/module/sw-settings-listing/component/sw-settings-listing-visibility-detail';
    export type * from 'src/module/sw-settings-listing/component/sw-settings-listing-visibility-detail';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-logging-entry-info' {
    export { default } from 'src/module/sw-settings-logging/component/sw-settings-logging-entry-info';
    export type * from 'src/module/sw-settings-logging/component/sw-settings-logging-entry-info';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-logging-mail-sent-info' {
    export { default } from 'src/module/sw-settings-logging/component/sw-settings-logging-mail-sent-info';
    export type * from 'src/module/sw-settings-logging/component/sw-settings-logging-mail-sent-info';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-login-registration' {
    export { default } from 'src/module/sw-settings-login-registration/page/sw-settings-login-registration';
    export type * from 'src/module/sw-settings-login-registration/page/sw-settings-login-registration';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-mailer' {
    export { default } from 'src/module/sw-settings-mailer/page/sw-settings-mailer';
    export type * from 'src/module/sw-settings-mailer/page/sw-settings-mailer';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-mailer-smtp' {
    export { default } from 'src/module/sw-settings-mailer/component/sw-settings-mailer-smtp';
    export type * from 'src/module/sw-settings-mailer/component/sw-settings-mailer-smtp';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-measurement' {
    export { default } from 'src/module/sw-settings-measurement/page/sw-settings-measurement';
    export type * from 'src/module/sw-settings-measurement/page/sw-settings-measurement';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-measurement-default-units' {
    export { default } from 'src/module/sw-settings-measurement/component/sw-settings-measurement-default-units';
    export type * from 'src/module/sw-settings-measurement/component/sw-settings-measurement-default-units';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-media' {
    export { default } from 'src/module/sw-settings-media/page/sw-settings-media';
    export type * from 'src/module/sw-settings-media/page/sw-settings-media';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-newsletter' {
    export { default } from 'src/module/sw-settings-newsletter/page/sw-settings-newsletter';
    export type * from 'src/module/sw-settings-newsletter/page/sw-settings-newsletter';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-number-range-create' {
    export { default } from 'src/module/sw-settings-number-range/page/sw-settings-number-range-create';
    export type * from 'src/module/sw-settings-number-range/page/sw-settings-number-range-create';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-number-range-detail' {
    export { default } from 'src/module/sw-settings-number-range/page/sw-settings-number-range-detail';
    export type * from 'src/module/sw-settings-number-range/page/sw-settings-number-range-detail';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-number-range-list' {
    export { default } from 'src/module/sw-settings-number-range/page/sw-settings-number-range-list';
    export type * from 'src/module/sw-settings-number-range/page/sw-settings-number-range-list';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-payment-create' {
    export { default } from 'src/module/sw-settings-payment/page/sw-settings-payment-create';
    export type * from 'src/module/sw-settings-payment/page/sw-settings-payment-create';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-payment-detail' {
    export { default } from 'src/module/sw-settings-payment/page/sw-settings-payment-detail';
    export type * from 'src/module/sw-settings-payment/page/sw-settings-payment-detail';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-payment-overview' {
    export { default } from 'src/module/sw-settings-payment/page/sw-settings-payment-overview';
    export type * from 'src/module/sw-settings-payment/page/sw-settings-payment-overview';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-payment-sorting-modal' {
    export { default } from 'src/module/sw-settings-payment/component/sw-settings-payment-sorting-modal';
    export type * from 'src/module/sw-settings-payment/component/sw-settings-payment-sorting-modal';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-price-rounding' {
    export { default } from 'src/module/sw-settings-currency/component/sw-settings-price-rounding';
    export type * from 'src/module/sw-settings-currency/component/sw-settings-price-rounding';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-product-feature-sets-detail' {
    export { default } from 'src/module/sw-settings-product-feature-sets/page/sw-settings-product-feature-sets-detail';
    export type * from 'src/module/sw-settings-product-feature-sets/page/sw-settings-product-feature-sets-detail';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-product-feature-sets-list' {
    export { default } from 'src/module/sw-settings-product-feature-sets/page/sw-settings-product-feature-sets-list';
    export type * from 'src/module/sw-settings-product-feature-sets/page/sw-settings-product-feature-sets-list';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-product-feature-sets-modal' {
    export { default } from 'src/module/sw-settings-product-feature-sets/component/sw-settings-product-feature-sets-modal';
    export type * from 'src/module/sw-settings-product-feature-sets/component/sw-settings-product-feature-sets-modal';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-product-feature-sets-values-card' {
    export { default } from 'src/module/sw-settings-product-feature-sets/component/sw-settings-product-feature-sets-values-card';
    export type * from 'src/module/sw-settings-product-feature-sets/component/sw-settings-product-feature-sets-values-card';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-salutation-detail' {
    export { default } from 'src/module/sw-settings-salutation/page/sw-settings-salutation-detail';
    export type * from 'src/module/sw-settings-salutation/page/sw-settings-salutation-detail';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-salutation-list' {
    export { default } from 'src/module/sw-settings-salutation/page/sw-settings-salutation-list';
    export type * from 'src/module/sw-settings-salutation/page/sw-settings-salutation-list';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-search' {
    export { default } from 'src/module/sw-settings-search/page/sw-settings-search';
    export type * from 'src/module/sw-settings-search/page/sw-settings-search';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-search-example-modal' {
    export { default } from 'src/module/sw-settings-search/component/sw-settings-search-example-modal';
    export type * from 'src/module/sw-settings-search/component/sw-settings-search-example-modal';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-search-excluded-search-terms' {
    export { default } from 'src/module/sw-settings-search/component/sw-settings-search-excluded-search-terms';
    export type * from 'src/module/sw-settings-search/component/sw-settings-search-excluded-search-terms';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-search-live-search' {
    export { default } from 'src/module/sw-settings-search/component/sw-settings-search-live-search';
    export type * from 'src/module/sw-settings-search/component/sw-settings-search-live-search';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-search-live-search-keyword' {
    export { default } from 'src/module/sw-settings-search/component/sw-settings-search-live-search-keyword';
    export type * from 'src/module/sw-settings-search/component/sw-settings-search-live-search-keyword';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-search-search-behaviour' {
    export { default } from 'src/module/sw-settings-search/component/sw-settings-search-search-behaviour';
    export type * from 'src/module/sw-settings-search/component/sw-settings-search-search-behaviour';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-search-searchable-content' {
    export { default } from 'src/module/sw-settings-search/component/sw-settings-search-searchable-content';
    export type * from 'src/module/sw-settings-search/component/sw-settings-search-searchable-content';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-search-searchable-content-customfields' {
    export { default } from 'src/module/sw-settings-search/component/sw-settings-search-searchable-content-customfields';
    export type * from 'src/module/sw-settings-search/component/sw-settings-search-searchable-content-customfields';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-search-searchable-content-general' {
    export { default } from 'src/module/sw-settings-search/component/sw-settings-search-searchable-content-general';
    export type * from 'src/module/sw-settings-search/component/sw-settings-search-searchable-content-general';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-search-view-general' {
    export { default } from 'src/module/sw-settings-search/view/sw-settings-search-view-general';
    export type * from 'src/module/sw-settings-search/view/sw-settings-search-view-general';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-search-view-live-search' {
    export { default } from 'src/module/sw-settings-search/view/sw-settings-search-view-live-search';
    export type * from 'src/module/sw-settings-search/view/sw-settings-search-view-live-search';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-seo' {
    export { default } from 'src/module/sw-settings-seo/page/sw-settings-seo';
    export type * from 'src/module/sw-settings-seo/page/sw-settings-seo';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-shipping-list' {
    export { default } from 'src/module/sw-settings-shipping/page/sw-settings-shipping-list';
    export type * from 'src/module/sw-settings-shipping/page/sw-settings-shipping-list';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-shipping-price-matrices' {
    export { default } from 'src/module/sw-settings-shipping/component/sw-settings-shipping-price-matrices';
    export type * from 'src/module/sw-settings-shipping/component/sw-settings-shipping-price-matrices';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-shipping-price-matrix' {
    export { default } from 'src/module/sw-settings-shipping/component/sw-settings-shipping-price-matrix';
    export type * from 'src/module/sw-settings-shipping/component/sw-settings-shipping-price-matrix';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-shipping-tax-cost' {
    export { default } from 'src/module/sw-settings-shipping/component/sw-settings-shipping-tax-cost';
    export type * from 'src/module/sw-settings-shipping/component/sw-settings-shipping-tax-cost';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-sitemap' {
    export { default } from 'src/module/sw-settings-sitemap/page/sw-settings-sitemap';
    export type * from 'src/module/sw-settings-sitemap/page/sw-settings-sitemap';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-snippet-detail' {
    export { default } from 'src/module/sw-settings-snippet/page/sw-settings-snippet-detail';
    export type * from 'src/module/sw-settings-snippet/page/sw-settings-snippet-detail';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-snippet-filter-switch' {
    export { default } from 'src/module/sw-settings-snippet/component/sidebar/sw-settings-snippet-filter-switch';
    export type * from 'src/module/sw-settings-snippet/component/sidebar/sw-settings-snippet-filter-switch';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-snippet-sidebar' {
    export { default } from 'src/module/sw-settings-snippet/component/sidebar/sw-settings-snippet-sidebar';
    export type * from 'src/module/sw-settings-snippet/component/sidebar/sw-settings-snippet-sidebar';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-state-machine-detail' {
    export { default } from 'src/module/sw-settings-state-machine/page/sw-settings-state-machine-detail';
    export type * from 'src/module/sw-settings-state-machine/page/sw-settings-state-machine-detail';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-state-machine-list' {
    export { default } from 'src/module/sw-settings-state-machine/page/sw-settings-state-machine-list';
    export type * from 'src/module/sw-settings-state-machine/page/sw-settings-state-machine-list';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-state-machine-state-detail' {
    export { default } from 'src/module/sw-settings-state-machine/component/sw-settings-state-machine-state-detail';
    export type * from 'src/module/sw-settings-state-machine/component/sw-settings-state-machine-state-detail';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-state-machine-state-list' {
    export { default } from 'src/module/sw-settings-state-machine/component/sw-settings-state-machine-state-list';
    export type * from 'src/module/sw-settings-state-machine/component/sw-settings-state-machine-state-list';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-tag-detail-assignments' {
    export { default } from 'src/module/sw-settings-tag/component/sw-settings-tag-detail-assignments';
    export type * from 'src/module/sw-settings-tag/component/sw-settings-tag-detail-assignments';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-tag-detail-modal' {
    export { default } from 'src/module/sw-settings-tag/component/sw-settings-tag-detail-modal';
    export type * from 'src/module/sw-settings-tag/component/sw-settings-tag-detail-modal';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-tag-list' {
    export { default } from 'src/module/sw-settings-tag/page/sw-settings-tag-list';
    export type * from 'src/module/sw-settings-tag/page/sw-settings-tag-list';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-tax-detail' {
    export { default } from 'src/module/sw-settings-tax/page/sw-settings-tax-detail';
    export type * from 'src/module/sw-settings-tax/page/sw-settings-tax-detail';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-tax-list' {
    export { default } from 'src/module/sw-settings-tax/page/sw-settings-tax-list';
    export type * from 'src/module/sw-settings-tax/page/sw-settings-tax-list';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-tax-rule-modal' {
    export { default } from 'src/module/sw-settings-tax/component/sw-settings-tax-rule-modal';
    export type * from 'src/module/sw-settings-tax/component/sw-settings-tax-rule-modal';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-tax-rule-type-individual-states' {
    export { default } from 'src/module/sw-settings-tax/component/sw-settings-tax-rule-type-individual-states';
    export type * from 'src/module/sw-settings-tax/component/sw-settings-tax-rule-type-individual-states';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-tax-rule-type-individual-states-cell' {
    export { default } from 'src/module/sw-settings-tax/component/sw-settings-tax-rule-type-individual-states-cell';
    export type * from 'src/module/sw-settings-tax/component/sw-settings-tax-rule-type-individual-states-cell';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-tax-rule-type-zip-code' {
    export { default } from 'src/module/sw-settings-tax/component/sw-settings-tax-rule-type-zip-code';
    export type * from 'src/module/sw-settings-tax/component/sw-settings-tax-rule-type-zip-code';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-tax-rule-type-zip-code-cell' {
    export { default } from 'src/module/sw-settings-tax/component/sw-settings-tax-rule-type-zip-code-cell';
    export type * from 'src/module/sw-settings-tax/component/sw-settings-tax-rule-type-zip-code-cell';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-tax-rule-type-zip-code-range' {
    export { default } from 'src/module/sw-settings-tax/component/sw-settings-tax-rule-type-zip-code-range';
    export type * from 'src/module/sw-settings-tax/component/sw-settings-tax-rule-type-zip-code-range';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-settings-tax-rule-type-zip-code-range-cell' {
    export { default } from 'src/module/sw-settings-tax/component/sw-settings-tax-rule-type-zip-code-range-cell';
    export type * from 'src/module/sw-settings-tax/component/sw-settings-tax-rule-type-zip-code-range-cell';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-sidebar-media-item' {
    export { default } from 'src/app/component/media/sw-sidebar-media-item';
    export type * from 'src/app/component/media/sw-sidebar-media-item';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-system-config' {
    export { default } from 'src/module/sw-settings/component/sw-system-config';
    export type * from 'src/module/sw-settings/component/sw-system-config';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-tax-rule-card' {
    export { default } from 'src/module/sw-settings-tax/component/sw-tax-rule-card';
    export type * from 'src/module/sw-settings-tax/component/sw-tax-rule-card';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-text-preview' {
    export { default } from 'src/app/component/utils/sw-text-preview';
    export type * from 'src/app/component/utils/sw-text-preview';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:components/sw-vector-field' {
    export { default } from 'src/app/component/media/sw-vector-field';
    export type * from 'src/app/component/media/sw-vector-field';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:framework/public-component' {
    export { definePrivateComponent, definePublicComponent } from 'src/core/public-component';
    export type * from 'src/core/public-component';
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:framework/service-names' {
    export {
        acl,
        appActionButtonService,
        appModulesService,
        cacheApiService,
        cacheService,
        captchaService,
        customEntityDefinitionService,
        customFieldDataProviderService,
        customSnippetApiService,
        documentV2ApiService,
        documentV2Service,
        excludedSearchTermService,
        extensionSdkService,
        extensionStoreActionService,
        feature,
        fileValidationService,
        filterFactory,
        filterService,
        importExport,
        licenseViolationService,
        liveSearchService,
        loginService,
        mediaFolderService,
        mediaPresignedUploadService,
        mediaService,
        menuService,
        messageStatsService,
        numberRangeService,
        productIndexService,
        productStreamPreviewService,
        recentlySearchService,
        repositoryFactory,
        ruleConditionDataProviderService,
        ruleConditionsConfigApiService,
        searchPreferencesService,
        searchRankingService,
        searchService,
        searchTypeService,
        seoUrlTemplateService,
        serviceRegistryClient,
        shopIdChangeService,
        shopwareServicesService,
        shortcutService,
        snackbarService,
        snippetService,
        snippetSetService,
        syncService,
        systemConfigApiService,
        tagApiService,
        timezoneService,
        translationService,
        updateService,
        userActivityApiService,
        userActivityService,
        userConfigService,
        userInputSanitizeService,
        userService,
    } from 'src/app/service-names';
    export type * from 'src/app/service-names';
}
