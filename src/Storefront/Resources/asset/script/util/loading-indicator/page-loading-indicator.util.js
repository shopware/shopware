import LoadingIndicatorUtil from "./loading-indicator.util";
import Backdrop from "../backdrop/backdrop.util";

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

        Backdrop.open();
        let backdrop = document.querySelector(`.${Backdrop.SELECTOR_CLASS()}`);
        backdrop.insertAdjacentHTML('beforeend', LoadingIndicatorUtil.getTemplate());
    }

    /**
     * Call parent method to remove the loading indicator
     * as well as the backdrop
     */
    remove() {
        super.remove();
        Backdrop.close();
    }
}

/**
 * Make the PageLoadingIndicatorUtil being a Singleton
 * @type {PageLoadingIndicatorUtilSingleton}
 */
const instance = new PageLoadingIndicatorUtilSingleton();
Object.freeze(instance);

export default class PageLoadingIndicatorUtil {

    /**
     * Open the PageLoadingIndicator
     */
    static open() {
        instance.create();
    }

    /**
     * Close the PageLoadingIndicator
     */
    static close() {
        instance.remove();
    }
}
