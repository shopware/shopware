import LoadingIndicator from "./LoadingIndicator";
import Backdrop from "../backdrop/Backdrop";

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
        let backdrop = document.querySelector(`.${Backdrop.SELECTOR_CLS()}`);
        backdrop.insertAdjacentHTML('beforeend', this.getTemplate());
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