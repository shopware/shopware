/**
 * @sw-package framework
 *
 * Types for the `shopware:*` modules, which expose the global `Shopware` object as ordinary
 * imports. `build/vite-plugins/virtual-shopware-modules` generates their runtime counterpart from
 * the same `shopware-modules.json`.
 *
 * Generated. Run `composer admin:generate-shopware-modules` after adding a utility, DAL class, mixin, or store.
 */

/* eslint-disable @typescript-eslint/consistent-type-imports */

declare module 'shopware:utils' {
    import type branch from 'src/core/service/util.service';

    const members: typeof branch;

    export = members;
}

declare module 'shopware:utils/createId' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['createId'];

    export = member;
}

declare module 'shopware:utils/throttle' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['throttle'];

    export = member;
}

declare module 'shopware:utils/debounce' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['debounce'];

    export = member;
}

declare module 'shopware:utils/flow' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['flow'];

    export = member;
}

declare module 'shopware:utils/get' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['get'];

    export = member;
}

declare module 'shopware:utils/object' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['object'];

    export = member;
}

declare module 'shopware:utils/debug' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['debug'];

    export = member;
}

declare module 'shopware:utils/format' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['format'];

    export = member;
}

declare module 'shopware:utils/dom' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['dom'];

    export = member;
}

declare module 'shopware:utils/string' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['string'];

    export = member;
}

declare module 'shopware:utils/types' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['types'];

    export = member;
}

declare module 'shopware:utils/fileReader' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['fileReader'];

    export = member;
}

declare module 'shopware:utils/sort' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['sort'];

    export = member;
}

declare module 'shopware:utils/array' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['array'];

    export = member;
}

declare module 'shopware:utils/moveItem' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['moveItem'];

    export = member;
}

declare module 'shopware:utils/VueHelper' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['VueHelper'];

    export = member;
}

declare module 'shopware:utils/EventBus' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['EventBus'];

    export = member;
}

declare module 'shopware:utils/genericRuleCondition' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['genericRuleCondition'];

    export = member;
}

declare module 'shopware:utils/unitConversion' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['unitConversion'];

    export = member;
}

declare module 'shopware:utils/extension' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['extension'];

    export = member;
}

declare module 'shopware:utils/mapInheritanceSlotPropsToMeteorProps' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['mapInheritanceSlotPropsToMeteorProps'];

    export = member;
}

declare module 'shopware:data' {
    import type branch from 'src/core/data/index';

    const members: typeof branch;

    export = members;
}

declare module 'shopware:data/ChangesetGenerator' {
    import type branch from 'src/core/data/index';

    const member: (typeof branch)['ChangesetGenerator'];

    export = member;
}

declare module 'shopware:data/Criteria' {
    import type branch from 'src/core/data/index';

    const member: (typeof branch)['Criteria'];

    export = member;
}

declare module 'shopware:data/Entity' {
    import type branch from 'src/core/data/index';

    const member: (typeof branch)['Entity'];

    export = member;
}

declare module 'shopware:data/EntityCollection' {
    import type branch from 'src/core/data/index';

    const member: (typeof branch)['EntityCollection'];

    export = member;
}

declare module 'shopware:data/EntityDefinition' {
    import type branch from 'src/core/data/index';

    const member: (typeof branch)['EntityDefinition'];

    export = member;
}

declare module 'shopware:data/EntityFactory' {
    import type branch from 'src/core/data/index';

    const member: (typeof branch)['EntityFactory'];

    export = member;
}

declare module 'shopware:data/EntityHydrator' {
    import type branch from 'src/core/data/index';

    const member: (typeof branch)['EntityHydrator'];

    export = member;
}

declare module 'shopware:data/Repository' {
    import type branch from 'src/core/data/index';

    const member: (typeof branch)['Repository'];

    export = member;
}

declare module 'shopware:data/ErrorResolver' {
    import type branch from 'src/core/data/index';

    const member: (typeof branch)['ErrorResolver'];

    export = member;
}

declare module 'shopware:data/FilterFactory' {
    import type branch from 'src/core/data/index';

    const member: (typeof branch)['FilterFactory'];

    export = member;
}

declare module 'shopware:mixins/notification' {
    const mixin: MixinContainer['notification'];

    export = mixin;
}

declare module 'shopware:mixins/validation' {
    const mixin: MixinContainer['validation'];

    export = mixin;
}

declare module 'shopware:mixins/user-settings' {
    const mixin: MixinContainer['user-settings'];

    export = mixin;
}

declare module 'shopware:mixins/sw-inline-snippet' {
    const mixin: MixinContainer['sw-inline-snippet'];

    export = mixin;
}

declare module 'shopware:mixins/translate-with-fallback' {
    const mixin: MixinContainer['translate-with-fallback'];

    export = mixin;
}

declare module 'shopware:mixins/notification-translation' {
    const mixin: MixinContainer['notification-translation'];

    export = mixin;
}

declare module 'shopware:mixins/salutation' {
    const mixin: MixinContainer['salutation'];

    export = mixin;
}

declare module 'shopware:mixins/ruleContainer' {
    const mixin: MixinContainer['ruleContainer'];

    export = mixin;
}

declare module 'shopware:mixins/remove-api-error' {
    const mixin: MixinContainer['remove-api-error'];

    export = mixin;
}

declare module 'shopware:mixins/position' {
    const mixin: MixinContainer['position'];

    export = mixin;
}

declare module 'shopware:mixins/placeholder' {
    const mixin: MixinContainer['placeholder'];

    export = mixin;
}

declare module 'shopware:mixins/listing' {
    const mixin: MixinContainer['listing'];

    export = mixin;
}

declare module 'shopware:mixins/cart-notification' {
    const mixin: MixinContainer['cart-notification'];

    export = mixin;
}

declare module 'shopware:mixins/sw-extension-error' {
    const mixin: MixinContainer['sw-extension-error'];

    export = mixin;
}

declare module 'shopware:mixins/cms-element' {
    const mixin: MixinContainer['cms-element'];

    export = mixin;
}

declare module 'shopware:mixins/cms-state' {
    const mixin: MixinContainer['cms-state'];

    export = mixin;
}

declare module 'shopware:mixins/generic-condition' {
    const mixin: MixinContainer['generic-condition'];

    export = mixin;
}

declare module 'shopware:mixins/sw-form-field' {
    const mixin: MixinContainer['sw-form-field'];

    export = mixin;
}

declare module 'shopware:mixins/discard-detail-page-changes' {
    const mixin: MixinContainer['discard-detail-page-changes'];

    export = mixin;
}

declare module 'shopware:mixins/rule-between-operator' {
    const mixin: MixinContainer['rule-between-operator'];

    export = mixin;
}

declare module 'shopware:stores/cmsPage' {
    const useStore: () => PiniaRootState['cmsPage'];

    export = useStore;
}

declare module 'shopware:stores/topBarButton' {
    const useStore: () => PiniaRootState['topBarButton'];

    export = useStore;
}

declare module 'shopware:stores/teaserPopover' {
    const useStore: () => PiniaRootState['teaserPopover'];

    export = useStore;
}

declare module 'shopware:stores/adminMenu' {
    const useStore: () => PiniaRootState['adminMenu'];

    export = useStore;
}

declare module 'shopware:stores/inAppPurchaseCheckout' {
    const useStore: () => PiniaRootState['inAppPurchaseCheckout'];

    export = useStore;
}

declare module 'shopware:stores/extensionComponentSections' {
    const useStore: () => PiniaRootState['extensionComponentSections'];

    export = useStore;
}

declare module 'shopware:stores/blockOverride' {
    const useStore: () => PiniaRootState['blockOverride'];

    export = useStore;
}

declare module 'shopware:stores/extensionEntryRoutes' {
    const useStore: () => PiniaRootState['extensionEntryRoutes'];

    export = useStore;
}

declare module 'shopware:stores/extensionSdkModules' {
    const useStore: () => PiniaRootState['extensionSdkModules'];

    export = useStore;
}

declare module 'shopware:stores/extensions' {
    const useStore: () => PiniaRootState['extensions'];

    export = useStore;
}

declare module 'shopware:stores/error' {
    const useStore: () => PiniaRootState['error'];

    export = useStore;
}

declare module 'shopware:stores/context' {
    const useStore: () => PiniaRootState['context'];

    export = useStore;
}

declare module 'shopware:stores/adminHelpCenter' {
    const useStore: () => PiniaRootState['adminHelpCenter'];

    export = useStore;
}

declare module 'shopware:stores/actionButtons' {
    const useStore: () => PiniaRootState['actionButtons'];

    export = useStore;
}

declare module 'shopware:stores/licenseViolation' {
    const useStore: () => PiniaRootState['licenseViolation'];

    export = useStore;
}

declare module 'shopware:stores/extensionMainModules' {
    const useStore: () => PiniaRootState['extensionMainModules'];

    export = useStore;
}

declare module 'shopware:stores/marketing' {
    const useStore: () => PiniaRootState['marketing'];

    export = useStore;
}

declare module 'shopware:stores/sdkLocation' {
    const useStore: () => PiniaRootState['sdkLocation'];

    export = useStore;
}

declare module 'shopware:stores/ruleConditionsConfig' {
    const useStore: () => PiniaRootState['ruleConditionsConfig'];

    export = useStore;
}

declare module 'shopware:stores/settingsItems' {
    const useStore: () => PiniaRootState['settingsItems'];

    export = useStore;
}

declare module 'shopware:stores/shopwareApps' {
    const useStore: () => PiniaRootState['shopwareApps'];

    export = useStore;
}

declare module 'shopware:stores/system' {
    const useStore: () => PiniaRootState['system'];

    export = useStore;
}

declare module 'shopware:stores/modals' {
    const useStore: () => PiniaRootState['modals'];

    export = useStore;
}

declare module 'shopware:stores/sidebar' {
    const useStore: () => PiniaRootState['sidebar'];

    export = useStore;
}

declare module 'shopware:stores/menuItem' {
    const useStore: () => PiniaRootState['menuItem'];

    export = useStore;
}

declare module 'shopware:stores/notification' {
    const useStore: () => PiniaRootState['notification'];

    export = useStore;
}

declare module 'shopware:stores/tabs' {
    const useStore: () => PiniaRootState['tabs'];

    export = useStore;
}

declare module 'shopware:stores/session' {
    const useStore: () => PiniaRootState['session'];

    export = useStore;
}

declare module 'shopware:stores/swCategoryDetail' {
    const useStore: () => PiniaRootState['swCategoryDetail'];

    export = useStore;
}

declare module 'shopware:stores/swSeoUrl' {
    const useStore: () => PiniaRootState['swSeoUrl'];

    export = useStore;
}

declare module 'shopware:stores/shopwareExtensions' {
    const useStore: () => PiniaRootState['shopwareExtensions'];

    export = useStore;
}

declare module 'shopware:stores/swOrderDetail' {
    const useStore: () => PiniaRootState['swOrderDetail'];

    export = useStore;
}

declare module 'shopware:stores/swOrder' {
    const useStore: () => PiniaRootState['swOrder'];

    export = useStore;
}

declare module 'shopware:stores/swShippingDetail' {
    const useStore: () => PiniaRootState['swShippingDetail'];

    export = useStore;
}

declare module 'shopware:stores/paymentOverviewCard' {
    const useStore: () => PiniaRootState['paymentOverviewCard'];

    export = useStore;
}

declare module 'shopware:stores/swProductDetail' {
    const useStore: () => PiniaRootState['swProductDetail'];

    export = useStore;
}

declare module 'shopware:stores/swProfile' {
    const useStore: () => PiniaRootState['swProfile'];

    export = useStore;
}

declare module 'shopware:stores/swPromotionDetail' {
    const useStore: () => PiniaRootState['swPromotionDetail'];

    export = useStore;
}

declare module 'shopware:stores/swFlow' {
    const useStore: () => PiniaRootState['swFlow'];

    export = useStore;
}

declare module 'shopware:stores/swBulkEdit' {
    const useStore: () => PiniaRootState['swBulkEdit'];

    export = useStore;
}

declare module 'shopware:stores/mediaModal' {
    const useStore: () => PiniaRootState['mediaModal'];

    export = useStore;
}
