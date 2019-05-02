export default class CmsSlotOptionValidatorHelper {

    /**
     * validates the slots option
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

        if (typeof options.slots !== 'object') {
            throw new Error('The "slots" option must be an object!');
        }

        options.slots.forEach((selectors, slotId) => {
            if (!Array.isArray(selectors)) {
                throw new Error(`The "slot" entry "${slotId}" must be an array of selectors!`);
            }

            if (selectors.length === 0) {
                throw new Error(`The "slot" entry "${slotId}" must have entries!`);
            }
        });

        return true;
    }
}
