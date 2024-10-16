/**
 * @package admin
 */

import notification from './notification.store';
import session from './session.store';
import system from './system.store';
import adminHelpCenter from './admin-help-center.store';
import context from './context.store';
import licenseViolation from './license-violation.store';
import error from './error.store';
import settingsItems from './settings-item.store';
import shopwareApps from './shopware-apps.store';
import marketing from './marketing.store';
import extensions from './extensions.store';
import tabs from './tabs.store';
import menuItem from './menu-item.store';
import extensionMainModules from './main-module.store';
import modals from './modals.store';
import actionButtons from './action-button.store';
import ruleConditionsConfig from './rule-conditions-config.store';
import sdkLocation from './sdk-location.store';
import usageData from './usage-data.store';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    notification,
    session,
    system,
    adminHelpCenter,
    licenseViolation,
    context,
    error,
    settingsItems,
    shopwareApps,
    marketing,
    extensions,
    tabs,
    menuItem,
    modals,
    extensionMainModules,
    actionButtons,
    ruleConditionsConfig,
    sdkLocation,
    usageData,
};
