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
 * The state is session scoped and cleared when a checkout starts (`begin_checkout`) and
 * when it completes (`purchase`), so every checkout reports each step at most once.
 */
export default class CheckoutStepHelper
{
    /**
     * @param {string} step
     * @returns {boolean}
     */
    static hasReported(step) {
        return CheckoutStepHelper._read().includes(step);
    }

    /**
     * @param {string} step
     */
    static markReported(step) {
        const steps = CheckoutStepHelper._read();

        if (steps.includes(step)) {
            return;
        }

        CheckoutStepHelper._write([...steps, step]);
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
