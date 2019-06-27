/**
 * This is just a sample file.
 * @todo: please remove before release!
 */
import Plugin from 'src/script/plugin-system/plugin.class';

/**
 * base plugin for example purposes
 *
 * gets executed in: platform/src/Storefront/Resources/src/script/base.js
  */
export default class SimplePlugin extends Plugin {

    /**
     * default options
     * can be overwritten/merged when plugin is extended
     * or on registration
     *
     * @type {*}
     */
    static options = {
        color: '#f00',
        some: 'default option'
    };

    /**
     * this method automatically gets executed
     * when the plugin gets executed
     */
    init() {
        this._getRandomColor();
    }

    /**
     * returns a random color
     *
     * @returns {string}
     * @private
     */
    _getRandomColor() {
        const range = '0123456789ABCDEF';
        let color = '#';
        for (let i = 0; i < 6; i += 1) {
            color += range[Math.floor(Math.random() * 16)];
        }

        this._randomColor = color;
        return color;
    }
}
