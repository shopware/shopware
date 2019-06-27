/**
 * This is just a sample file.
 * @todo: please remove before release!
 */
import Plugin from 'src/script/plugin-system/plugin.class';


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
