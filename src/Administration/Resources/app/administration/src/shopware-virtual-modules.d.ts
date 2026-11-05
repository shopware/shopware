/**
 * @sw-package framework
 *
 * @experimental stableVersion:v6.8.0
 *
 * Types for the `shopware:*` modules, which expose the global `Shopware` object as ordinary
 * imports. `build/vite-plugins/virtual-shopware-modules` generates their runtime counterpart from
 * the same `shopware-modules.json`.
 *
 * Generated. Run `composer admin:generate-shopware-modules` after adding a utility, DAL class, composable, mixin, or store.
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
    export const Repository: (typeof members)['Repository'];
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
declare module 'shopware:data/Repository' {
    import type branch from 'src/core/data/index';

    const member: (typeof branch)['Repository'];

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
declare module 'shopware:stores/ruleConditionsConfig' {
    const useStore: () => PiniaRootState['ruleConditionsConfig'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:stores/shopwareApps' {
    const useStore: () => PiniaRootState['shopwareApps'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:stores/notification' {
    const useStore: () => PiniaRootState['notification'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopware:stores/swCategoryDetail' {
    const useStore: () => PiniaRootState['swCategoryDetail'];

    export default useStore;
}
