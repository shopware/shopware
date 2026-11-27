/**
 * @sw-package framework
 */

/**
 * @private
 */
export type SystemHealthCheckView = {
    // the component that shows the check's result; it gets the check as its `check` prop
    component?: string;
    // the page that fixes a failing check, offered as the action of its resolution
    resolveRoute?: string;
};

/**
 * How the Administration shows each check. A check without an entry gets the default view.
 * Extensions register their checks the same way:
 *
 *     Shopware.Service('systemHealthCheckRegistry').register('MyCheck', { component: 'my-check-result' });
 *
 * @private
 */
export default class SystemHealthCheckRegistry {
    private readonly views = new Map<string, SystemHealthCheckView>();

    register(checkName: string, view: SystemHealthCheckView): void {
        this.views.set(checkName, { ...this.views.get(checkName), ...view });
    }

    get(checkName: string): SystemHealthCheckView {
        return this.views.get(checkName) ?? {};
    }
}
