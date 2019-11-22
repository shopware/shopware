import LoadingIndicatorUtil from 'src/utility/loading-indicator/loading-indicator.util';
import BackdropUtil from 'src/utility/backdrop/backdrop.util';

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
     */
    create() {
        if (this.exists()) return;

        BackdropUtil.create();
        const backdrop = document.querySelector(`.${BackdropUtil.SELECTOR_CLASS()}`);
        backdrop.insertAdjacentHTML('beforeend', LoadingIndicatorUtil.getTemplate());
    }

    /**
     * Call parent method to remove the loading indicator
     * as well as the backdrop
     */
    remove() {
        super.remove();
        BackdropUtil.remove();
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
     */
    static create() {
        PageLoadingIndicatorUtilInstance.create();
    }

    /**
     * Close the PageLoadingIndicator
     */
    static remove() {
        PageLoadingIndicatorUtilInstance.remove();
    }
}
