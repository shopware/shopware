/**
 * This is just a sample file.
 * @todo: please remove before release!
 */
import SimplePlugin from 'src/script/plugin/_example/simple.plugin';

/**
 * extends a plugin with class inheritance
 *
 * gets executed in: platform/src/Storefront/Resources/src/script/base.js
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
