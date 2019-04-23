import Plugin from 'src/script/helper/plugin/plugin.class';


/**
 * overrides a plugin
 * gets executed in: platform/src/Storefront/Resources/src/script/base.js
 */
export default class OverriddenPlugin extends Plugin {

    /**
     * default options
     * can be overwritten/merged when plugin is extended
     * or on registration
     *
     * @type {*}
     */
    static options = {
        other: 'option'
    };

    init() {
        this._getRandomColor();
    }

    _getRandomColor() {
        this._randomColor = 'overridden';
    }
}
