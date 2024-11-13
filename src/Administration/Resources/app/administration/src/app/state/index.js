/**
 * @package admin
 */

import notification from './notification.store';
import session from './session.store';
import system from './system.store';
import settingsItems from './settings-item.store';
import shopwareApps from './shopware-apps.store';
import tabs from './tabs.store';
import menuItem from './menu-item.store';
import modals from './modals.store';
import usageData from './usage-data.store';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    notification,
    session,
    system,
    settingsItems,
    shopwareApps,
    tabs,
    menuItem,
    modals,
    usageData,
};
