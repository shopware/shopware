/**
 * @typedef {object} AnalyticsEventContext
 * @property {string} activeRoute
 */
export default class AnalyticsEvent
{
    active = true;

    /**
     * The number of declared parameters decides how `GoogleAnalyticsPlugin` calls this method:
     * one or none receives the context object, two or more receive the legacy arguments
     * `(controllerName, actionName, activeRoute)`.
     *
     * @deprecated tag:v6.9.0 - The legacy signature `supports(controllerName, actionName, activeRoute)` will be removed.
     * Declare `supports({ activeRoute })` instead.
     *
     * @param {AnalyticsEventContext} context
     * @returns {boolean}
     */
    // eslint-disable-next-line no-unused-vars
    supports(context) {
        console.warn('[Google Analytics Plugin] Method \'supports\' was not overridden by `' + this.constructor.name + '`. Default return set to false.');
        return false;
    }

    execute() {
        console.warn('[Google Analytics Plugin] Method \'execute\' was not overridden by `' + this.constructor.name + '`.');
    }

    /**
     * Ecommerce parameters are normalized here, so every event and every extension event sends
     * the format GA4 documents without repeating the conversion at each call site.
     * Most values are read from `data-*` attributes and arrive as strings, while GA4 expects
     * numbers for amounts and quantities. Empty optional values are dropped instead of being
     * sent as empty properties, and amounts summed up in the browser lose their floating point noise.
     *
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
                        return [key, this._normalizeAmount(value)];
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
                    if (['price', 'discount'].includes(key)) {
                        return [key, this._normalizeAmount(value)];
                    }

                    if (['quantity', 'index'].includes(key)) {
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

    /**
     * Amounts summed up in the browser carry floating point noise, such as `961.3899999999999`.
     * Twelve significant digits drop the noise and keep every decimal a currency rounds to,
     * such as the three decimals of KWD.
     *
     * @param {number|string} value
     * @returns {number|string}
     * @private
     */
    _normalizeAmount(value) {
        const number = this._normalizeNumber(value);
        return typeof number === 'number' ? Number(number.toPrecision(12)) : number;
    }

    disable() {
        this.active = false;
    }

    enable() {
        this.active = true;
    }
}
