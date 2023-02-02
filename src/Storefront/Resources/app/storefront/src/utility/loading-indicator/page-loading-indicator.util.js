import LoadingIndicatorUtil from 'src/utility/loading-indicator/loading-indicator.util';
import BackdropUtil from 'src/utility/backdrop/backdrop.util';

/**
 * @package storefront
 */
class PageLoadingIndicatorUtilSingleton extends LoadingIndicatorUtil {

    /**
     * Constructor
     */
    constructor() {
        super(document.body);
    }

    /**
     * Call parent method to attach the loading indicator
     * as well as the backdrop
     *
     * @param useBackdrop
     */
    create(useBackdrop = true) {
        if (this.exists()) return;

        if (useBackdrop) {
            BackdropUtil.create();
            const backdrop = document.querySelector(`.${BackdropUtil.SELECTOR_CLASS()}`);
            backdrop.insertAdjacentHTML('beforeend', LoadingIndicatorUtil.getTemplate());
        }
    }

    /**
     * Call parent method to remove the loading indicator
     * as well as the backdrop
     *
     * @param useBackdrop
     */
    remove(useBackdrop = true) {
        super.remove();

        if (useBackdrop) {
            BackdropUtil.remove();
        }
    }
}

/**
 * Create the PageLoadingIndicatorUtil instance.
 * @type {Readonly<PageLoadingIndicatorUtilSingleton>}
 */
export const PageLoadingIndicatorUtilInstance = Object.freeze(new PageLoadingIndicatorUtilSingleton());

export default class PageLoadingIndicatorUtil {

    /**
     * Open the PageLoadingIndicator
     *
     * @param useBackdrop
     */
    static create(useBackdrop = true) {
        PageLoadingIndicatorUtilInstance.create(useBackdrop);
    }

    /**
     * Close the PageLoadingIndicator
     * If useBackdrop is set to false, no existing backdrops are removed
     *
     * @param useBackdrop
     */
    static remove(useBackdrop = true) {
        PageLoadingIndicatorUtilInstance.remove(useBackdrop);
    }
}
