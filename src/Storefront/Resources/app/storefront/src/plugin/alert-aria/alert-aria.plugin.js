import Plugin from 'src/plugin-system/plugin.class';

/**
* This plugin can be used to announce alerts to the screenreader that are rendered in the DOM on page load.
*
* @example
* {% sw_include '@Storefront/storefront/utilities/alert.html.twig' with {
*     type: "primary",
*     content: "An important message on initial page load",
*     announceOnLoad: true
* } %}
*
* @internal
*/
export default class AlertAriaPlugin extends Plugin {

    static options = {
        ariaLive: 'polite',
    };

    init() {
        this._container = this.el.querySelector('.alert-content-container');

        if (!this._container) {
            console.warn(`[${this._pluginName}] The alert content container cannot be found.`);
            return;
        }

        if (!this.el.hasAttribute('aria-live')) {
            console.warn(`[${this._pluginName}] The "aria-live" attribute is not found on the alert. The alert will not be announced.`);
        }

        this._announceAlert();
    }

    _announceAlert() {
        // Ensure assertive alerts are announced before polite alerts.
        const delay = this.options.ariaLive === 'assertive' ? 1000 : 1500;

        // Hide the alert content from screenreader initially.
        this._container.setAttribute('aria-hidden', 'true');

        // After timeout, disable aria-hidden to trigger the parent aria-live region.
        setTimeout(() => {
            this._container.setAttribute('aria-hidden', 'false');
        }, delay);
    }
}
