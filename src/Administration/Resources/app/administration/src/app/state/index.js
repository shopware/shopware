/**
 * @package admin
 */

import notification from './notification.store';
import session from './session.store';
import tabs from './tabs.store';
import menuItem from './menu-item.store';
import usageData from './usage-data.store';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    notification,
    session,
    tabs,
    menuItem,
    usageData,
};
