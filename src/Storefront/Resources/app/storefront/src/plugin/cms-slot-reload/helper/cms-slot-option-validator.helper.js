import Iterator from 'src/helper/iterator.helper';

/**
 * @package content
 */
export default class CmsSlotOptionValidatorHelper {

    /**
     * validates the cms reload options
     * to make sure it's configured correctly
     *
     * @returns {boolean}
     * @private
     */
    static validate(options) {
        if (!options.navigationId && !options.cmsPageId) {
            throw new Error('The "navigationId" or "cmsPageId" option must be given!');
        }

        if (!Array.isArray(options.events)) {
            throw new Error('The "events" option has to be an array of event types!');
        }

        if (options.events.length === 0) {
            throw new Error('The "events" option must have entries!');
        }

        if (!Array.isArray(options.hiddenParams)) {
            throw new Error('The "hiddenParams" option has to be an array!');
        }

        if (typeof options.elements !== 'object') {
            throw new Error('The "elements" option must be an object!');
        }

        Iterator.iterate(options.elements, (selectors, elementId) => {
            if (!Array.isArray(selectors)) {
                throw new Error(`The "elements" entry "${elementId}" must be an array of selectors!`);
            }

            if (selectors.length === 0) {
                throw new Error(`The "elements" entry "${elementId}" must have entries!`);
            }
        });

        return true;
    }
}
