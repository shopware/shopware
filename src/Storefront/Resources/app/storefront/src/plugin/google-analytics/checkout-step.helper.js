const STORAGE_KEY = 'swGaReportedCheckoutSteps';

let memorySteps = [];
let storageSupported = null;

/**
 * Mirrors the support probe of `src/helper/storage/storage.helper.js`. Accessing
 * `sessionStorage` throws in private browsing modes and when storage is disabled.
 *
 * @returns {boolean}
 */
function isStorageSupported() {
    if (storageSupported !== null) {
        return storageSupported;
    }

    try {
        const testKey = `${STORAGE_KEY}__test`;
        window.sessionStorage.setItem(testKey, '1');
        window.sessionStorage.removeItem(testKey);
        storageSupported = true;
    } catch (e) {
        storageSupported = false;
    }

    return storageSupported;
}

/**
 * Remembers which checkout events have already been reported for the running checkout.
 *
 * The shipping and payment method forms on the confirm page use auto-submit
 * (`data-form-auto-submit`), so selecting a method reloads the page and the confirm route
 * runs again. Reporting `add_shipping_info` and `add_payment_info` on every page load
 * therefore sends them once per reload, which pushes their counts above `begin_checkout`
 * and breaks GA4 funnel reports.
 *
 * A step is remembered together with the value it reported, so a reload that changes nothing
 * stays silent while actually switching the shipping or payment method reports the new one.
 * Reporting only the first load instead would describe the preselected method forever and
 * leave the GA4 `shipping_tier` and `payment_type` dimensions reporting shop defaults.
 *
 * The state is session scoped and cleared when a checkout starts (`begin_checkout`) and
 * when it completes (`purchase`).
 */
export default class CheckoutStepHelper
{
    /**
     * @param {string} step
     * @param {string} value the reported `shipping_tier` or `payment_type`
     * @returns {boolean}
     */
    static hasReported(step, value = '') {
        return CheckoutStepHelper._read().includes(CheckoutStepHelper._key(step, value));
    }

    /**
     * @param {string} step
     * @param {string} value the reported `shipping_tier` or `payment_type`
     */
    static markReported(step, value = '') {
        const steps = CheckoutStepHelper._read();
        const key = CheckoutStepHelper._key(step, value);

        if (steps.includes(key)) {
            return;
        }

        CheckoutStepHelper._write([...steps, key]);
    }

    /**
     * @param {string} step
     * @param {string} value
     * @returns {string}
     * @private
     */
    static _key(step, value) {
        return `${step}:${value}`;
    }

    /**
     * Starts a new checkout cycle, so the next checkout reports its steps again.
     */
    static reset() {
        CheckoutStepHelper._write([]);
    }

    /**
     * @returns {string[]}
     * @private
     */
    static _read() {
        if (!isStorageSupported()) {
            return memorySteps;
        }

        try {
            const steps = JSON.parse(window.sessionStorage.getItem(STORAGE_KEY));

            return Array.isArray(steps) ? steps : [];
        } catch (e) {
            return [];
        }
    }

    /**
     * @param {string[]} steps
     * @private
     */
    static _write(steps) {
        if (!isStorageSupported()) {
            memorySteps = steps;

            return;
        }

        window.sessionStorage.setItem(STORAGE_KEY, JSON.stringify(steps));
    }
}
