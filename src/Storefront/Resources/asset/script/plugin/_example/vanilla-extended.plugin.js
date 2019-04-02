import SimplePlugin from "./simple.plugin";

/**
 * extends a plugin with class inheritance
 *
 * gets executed in: platform/src/Storefront/Resources/asset/script/base.js
 */
export default class VanillaExtendedPlugin extends SimplePlugin {
    /**
     * default options
     * can be overwritten/merged when plugin is extended
     * or on registration
     *
     * @type {*}
     */
    static options = {
        other: 'option',
        color: '#0ff'
    };

    _getRandomColor() {
        this._randomColor = this.options.color;
    }
}
