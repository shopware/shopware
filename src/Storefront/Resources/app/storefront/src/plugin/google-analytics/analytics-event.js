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
        console.warn('[Google Analytics Plugin] Method \'supports\' was not overridden by `' + this.constructor.name + '`. Default return set to false.');
        return false;
    }
    /* eslint-enable no-unused-vars */

    execute() {
        console.warn('[Google Analytics Plugin] Method \'execute\' was not overridden by `' + this.constructor.name + '`.');
    }

    /**
     * @param {string} name
     * @param {Object} parameters
     * @param {Object} options
     * @param {boolean} options.ecommerce
     */
    pushEvent(name, parameters, { ecommerce = true } = {}) {
        if (!window.gtagIsTagManager) {
            gtag('event', name, parameters);
            return;
        }

        if (ecommerce) {
            window.dataLayer.push({ ecommerce: null });
            window.dataLayer.push({
                event: name,
                ecommerce: parameters,
            });
            return;
        }

        window.dataLayer.push({
            event: name,
            ...parameters,
        });
    }

    disable() {
        this.active = false;
    }
}
