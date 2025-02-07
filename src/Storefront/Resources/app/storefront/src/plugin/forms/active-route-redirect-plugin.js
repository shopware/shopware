import Plugin from 'src/plugin-system/plugin.class';

/**
 * This plugin adds redirect parameter to the form before the form is submitted.
 */
export default class ActiveRouteRedirectPlugin extends Plugin {

    static options = {
        redirectTo: window.activeRoute,
        redirectParameter: JSON.parse(window.activeRouteParameters),
    };

    init() {
        this._registerEvents();
    }

    /**
     * registers all needed events
     *
     * @private
     */
    _registerEvents() {
        this.el.addEventListener('submit', this._onSubmit.bind(this));
    }

    /**
     * @private
     */
    _onSubmit() {
        this._createInputForRedirectTo();

        for (const parameter in this.options.redirectParameter) {
            const input = this._createInputForRedirectParameter(parameter, this.options.redirectParameter[parameter]);
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
