export default class AnalyticsEvent
{
    active = true;

    /* eslint-disable no-unused-vars */
    /**
     * @param {string} controllerName
     * @param {string} actionName
     * @returns {boolean}
     */
    supports(controllerName, actionName) {
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
