/**
 * @package admin
 */

import notification from './notification.store';
import session from './session.store';
import system from './system.store';
import adminMenu from './admin-menu.store';
import context from './context.store';
import licenseViolation from './license-violation.store';
import error from './error.store';
import settingsItems from './settings-item.store';
import shopwareApps from './shopware-apps.store';
import extensionEntryRoutes from './extension-entry-routes';
import marketing from './marketing.store';
import extensionComponentSections from './extension-component-sections.store';
import extensions from './extensions.store';
import tabs from './tabs.store';
import menuItem from './menu-item.store';
import extensionSdkModules from './extension-sdk-module.store';
import extensionMainModules from './main-module.store';
import modals from './modals.store';
import actionButtons from './action-button.store';
import ruleConditionsConfig from './rule-conditions-config.store';
import sdkLocation from './sdk-location.store';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    notification,
    session,
    system,
    adminMenu,
    licenseViolation,
    context,
    error,
    settingsItems,
    shopwareApps,
    extensionEntryRoutes,
    marketing,
    extensionComponentSections,
    extensions,
    tabs,
    menuItem,
    extensionSdkModules,
    modals,
    extensionMainModules,
    actionButtons,
    ruleConditionsConfig,
    sdkLocation,
};
