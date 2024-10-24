/**
 * @package admin
 */

import notification from './notification.store';
import session from './session.store';
import system from './system.store';
import licenseViolation from './license-violation.store';
import settingsItems from './settings-item.store';
import shopwareApps from './shopware-apps.store';
import marketing from './marketing.store';
import tabs from './tabs.store';
import menuItem from './menu-item.store';
import extensionMainModules from './main-module.store';
import modals from './modals.store';
import ruleConditionsConfig from './rule-conditions-config.store';
import sdkLocation from './sdk-location.store';
import usageData from './usage-data.store';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    notification,
    session,
    system,
    licenseViolation,
    settingsItems,
    shopwareApps,
    marketing,
    tabs,
    menuItem,
    modals,
    extensionMainModules,
    ruleConditionsConfig,
    sdkLocation,
    usageData,
};
