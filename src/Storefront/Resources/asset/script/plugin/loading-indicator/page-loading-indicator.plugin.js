import LoadingIndicator from "./loading-indicator.plugin";
import Backdrop from "../backdrop/backdrop.plugin";

class PageLoadingIndicatorSingleton extends LoadingIndicator {

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
        backdrop.insertAdjacentHTML('beforeend', LoadingIndicator.getTemplate());
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
 * Make the PageLoadingIndicator being a Singleton
 * @type {PageLoadingIndicatorSingleton}
 */
const instance = new PageLoadingIndicatorSingleton();
Object.freeze(instance);

export default class PageLoadingIndicator {

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