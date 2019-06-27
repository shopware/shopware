/**
 * This is just a sample file.
 * @todo: please remove before release!
 */
import Plugin from 'src/script/plugin-system/plugin.class';

/**
 * extends a plugin without class inheritance
 * gets executed in: platform/src/Storefront/Resources/src/script/base.js
 */
export default class ExtendedPlugin extends Plugin {
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
