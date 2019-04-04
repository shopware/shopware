import deepmerge from 'deepmerge';

export default class ImageSliderSettingsHelper {

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
        Object.keys(options.responsive).forEach((viewport) => {
            const viewportWidth = window.breakpoints[viewport.toLowerCase()];
            options.responsive[viewportWidth] = options.responsive[viewport];
            delete options.responsive[viewport];
        });

        return options;
    }
}
