import Plugin from '../../helper/plugin/plugin.class';


/**
 * overrides a plugin
 * gets executed in: platform/src/Storefront/Resources/asset/script/base.js
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
        console.log(this);
    }

    _getRandomColor() {
        this._randomColor = 'overridden';
    }
}
