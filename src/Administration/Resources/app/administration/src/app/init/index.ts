/**
 * @package admin
 *
 * These types of initializers are called in the middle of the initialization process.
 * They are not allowed to depend on another initializers to suppress circular references.
 */
import initComponentHelper from 'src/app/init/component-helper.init';
import initHttpClient from 'src/app/init/http.init';
import initRepository from 'src/app/init/repository.init';
import initMixin from 'src/app/init/mixin.init';
import initCoreModules from 'src/app/init/modules.init';
import initLogin from 'src/app/init/login.init';
import initRouter from 'src/app/init/router.init';
import initFilter from 'src/app/init/filter.init';
import initDirectives from 'src/app/init/directive.init';
import initLocale from 'src/app/init/locale.init';
import initComponents from 'src/app/init/component.init';
import initShortcut from 'src/app/init/shortcut.init';
import initFilterFactory from 'src/app/init/filter-factory.init';
import initializeNotifications from 'src/app/init/notification.init';
import initializeContext from 'src/app/init/context.init';
import initializeWindow from 'src/app/init/window.init';
import initializeExtensionComponentSections from 'src/app/init/extension-component-sections.init';
import initTabs from 'src/app/init/tabs.init';
import initCms from './cms.init';
import initMenu from './menu-item.init';
import initModals from './modals.init';
import initSettingItems from './settings-item.init';
import initMainModules from './main-module.init';
import initializeActionButtons from './action-button.init';
import initializeActions from './actions.init';
import initializeExtensionDataHandling from './extension-data-handling.init';
import initializeInAppPurchaseCheckout from './in-app-purchase-checkout.init';
import initializeTopBarButtons from './topbar-button.init';
import initializeTeaserPopovers from './teaser-popover.init';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    coreMixin: initMixin,
    coreDirectives: initDirectives,
    coreFilter: initFilter,
    baseComponents: initComponents,
    coreModuleRoutes: initCoreModules,
    login: initLogin,
    router: initRouter,
    locale: initLocale,
    repositoryFactory: initRepository,
    shortcut: initShortcut,
    httpClient: initHttpClient,
    componentHelper: initComponentHelper,
    filterFactory: initFilterFactory,
    notification: initializeNotifications,
    context: initializeContext,
    window: initializeWindow,
    extensionComponentSections: initializeExtensionComponentSections,
    tabs: initTabs,
    cms: initCms,
    menu: initMenu,
    settingItems: initSettingItems,
    modals: initModals,
    mainModules: initMainModules,
    actionButton: initializeActionButtons,
    actions: initializeActions,
    extensionDataHandling: initializeExtensionDataHandling,
    inAppPurchaseCheckout: initializeInAppPurchaseCheckout,
    topbarButton: initializeTopBarButtons,
    teaserPopover: initializeTeaserPopovers,
};
