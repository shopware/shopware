import Plugin from 'src/plugin-system/plugin.class';

/**
 * This plugin adds dynamic redirect parameters to the form before the form is submitted.
 *
 * @package framework
 */
export default class FormAddDynamicRedirectPlugin extends Plugin {

    static options = {
        /**
         * Name of the route to redirect to, e.g. 'frontend.detail.page'.
         * @type {string}
         */
        redirectTo: window.activeRoute,

        /**
         * Additional parameters to add to the redirect, given as JSON string, e.g. {"productId": 0194da86d1cc70beb15bc0660882b87a}.
         * @type {string}
         */
        redirectParameter: window.activeRouteParameters,
    };

    init() {
        this.el.addEventListener('submit', this._onSubmit.bind(this));
    }

    /**
     * Adds redirect parameters to the form before it is submitted.
     * @private
     */
    _onSubmit() {
        this._createInputForRedirectTo();

        const redirectParameters = JSON.parse(this.options.redirectParameter);
        for (const parameter in redirectParameters) {
            const input = this._createInputForRedirectParameter(parameter, redirectParameters[parameter]);
            this.el.appendChild(input);
        }
    }

    /**
     * @private
     */
    _createInputForRedirectTo() {
        const activeRouteInput = document.createElement('input');
        activeRouteInput.setAttribute('type', 'hidden');
        activeRouteInput.setAttribute('name', 'redirectTo');
        activeRouteInput.setAttribute('value', this.options.redirectTo);
        this.el.appendChild(activeRouteInput);
    }

    /**
     * @private
     */
    _createInputForRedirectParameter(name, value) {
        const parameterInput = document.createElement('input');

        parameterInput.setAttribute('type', 'hidden');
        parameterInput.setAttribute('name', `redirectParameters[${name}]`);
        parameterInput.setAttribute('value', value);

        return parameterInput;
    }
}
