import deepmerge from 'deepmerge';
import Iterator from 'src/helper/iterator.helper';

export default class SliderSettingsHelper {

    /**
     * returns the merged object between the base options
     * and the responsive viewport options of a slider
     *
     * @param options
     * @param viewport
     */
    static getViewportSettings(options, viewport) {
        const settings = Object.assign({}, options);
        const responsiveSettings = options.responsive;
        delete settings.responsive;

        const viewportWidth = window.breakpoints[viewport.toLowerCase()];
        const selectedViewportSettings = responsiveSettings[viewportWidth];

        if (!selectedViewportSettings) {
            return settings;
        }

        return deepmerge(settings, selectedViewportSettings);
    }

    /**
     * converts the responsive slider options keys
     * from a viewport string into a viewport px value
     *
     * @param options
     * @return {*}
     */
    static prepareBreakpointPxValues(options) {
        Iterator.iterate(options.responsive, (viewportOptions,viewport) => {
            const viewportWidth = window.breakpoints[viewport.toLowerCase()];
            options.responsive[viewportWidth] = viewportOptions;
            delete options.responsive[viewport];
        });

        return options;
    }
}
