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
        const eventParameters = ecommerce ? this._normalizeEcommerceParameters(parameters) : parameters;

        if (!window.gtagIsTagManager) {
            gtag('event', name, eventParameters);
            return;
        }

        if (ecommerce) {
            window.dataLayer.push({ ecommerce: null });
            window.dataLayer.push({
                event: name,
                ecommerce: eventParameters,
            });
            return;
        }

        window.dataLayer.push({
            event: name,
            ...parameters,
        });
    }

    /**
     * @param {Object} parameters
     * @returns {Object}
     * @private
     */
    _normalizeEcommerceParameters(parameters) {
        return Object.fromEntries(
            Object.entries(parameters)
                .filter(([, value]) => value !== undefined && value !== null && value !== '')
                .map(([key, value]) => {
                    if (key === 'items') {
                        return [key, value.map(item => this._normalizeItem(item))];
                    }

                    if (['value', 'tax', 'shipping'].includes(key)) {
                        return [key, this._normalizeNumber(value)];
                    }

                    return [key, value];
                }),
        );
    }

    /**
     * @param {Object} item
     * @returns {Object}
     * @private
     */
    _normalizeItem(item) {
        return Object.fromEntries(
            Object.entries(item)
                .filter(([, value]) => value !== undefined && value !== null && value !== '')
                .map(([key, value]) => {
                    if (['price', 'quantity', 'discount', 'index'].includes(key)) {
                        return [key, this._normalizeNumber(value)];
                    }

                    return [key, value];
                }),
        );
    }

    /**
     * @param {number|string} value
     * @returns {number|string}
     * @private
     */
    _normalizeNumber(value) {
        const number = Number(value);
        return Number.isNaN(number) ? value : number;
    }

    disable() {
        this.active = false;
    }
}
