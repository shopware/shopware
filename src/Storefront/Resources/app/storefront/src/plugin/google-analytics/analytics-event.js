export default class AnalyticsEvent
{
    active = true;

    /* eslint-disable no-unused-vars */
    /**
     * @param {string} controllerName @deprecated tag:v6.8.0 - Will be removed, use activeRoute instead.
     * @param {string} actionName @deprecated tag:v6.8.0 - Will be removed, use activeRoute instead.
     * @param {string} activeRoute
     * @returns {boolean}
     */
    supports(controllerName, actionName, activeRoute) {
        if (controllerName || actionName) {
            console.warn('[DEPRECATED] tag:v6.8.0 - The parameters "controllerName" and "actionName" in the Google Analytics supports() method are deprecated and will be removed. Please use the "activeRoute" parameter instead.');
        }

        console.warn('[Google Analytics Plugin] Method \'supports\' was not overridden by `' + this.constructor.name + '`. Default return set to false.');
        return false;
    }
    /* eslint-enable no-unused-vars */

    execute() {
        console.warn('[Google Analytics Plugin] Method \'execute\' was not overridden by `' + this.constructor.name + '`.');
    }

    disable() {
        this.active = false;
    }
}
