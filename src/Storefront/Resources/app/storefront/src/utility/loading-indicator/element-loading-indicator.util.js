import LoadingIndicatorUtil from 'src/utility/loading-indicator/loading-indicator.util';

const ELEMENT_LOADER_CLASS = 'element-loader-backdrop';
const VISUALLY_HIDDEN_CLASS = 'visually-hidden';

/**
 * @package storefront
 */
export default class ElementLoadingIndicatorUtil extends LoadingIndicatorUtil {

    /**
     * adds the loader from the element
     *
     * @param {HTMLElement} el
     */
    static create(el) {
        el.classList.add('has-element-loader');
        if (ElementLoadingIndicatorUtil.exists(el)) return;
        ElementLoadingIndicatorUtil.appendLoader(el);
        setTimeout(() => {
            const loader = el.querySelector(`.${ELEMENT_LOADER_CLASS}`);
            if (!loader) {
                return;
            }

            loader.classList.add('element-loader-backdrop-open');
        }, 1);
    }

    /**
     * removes the loader from the element
     *
     * @param {HTMLElement} el
     */
    static remove(el) {
        el.classList.remove('has-element-loader');
        const loader = el.querySelector(`.${ELEMENT_LOADER_CLASS}`);
        if (!loader) {
            return;
        }

        loader.remove();
    }

    /**
     * checks if a loader is already present
     *
     * @param {HTMLElement} el
     *
     * @returns {boolean}
     */
    static exists(el) {
        return (el.querySelectorAll(`.${ELEMENT_LOADER_CLASS}`).length > 0);
    }


    /**
     * returns the loader template
     *
     * @returns {string}
     */
    static getTemplate() {
        return `
        <div class="${ELEMENT_LOADER_CLASS}">
            <div class="loader" role="status">
                <span class="${VISUALLY_HIDDEN_CLASS}">Loading...</span>
            </div>
        </div>
        `;
    }

    /**
     * inserts the loader into the passed element
     *
     * @param {HTMLElement} el
     */
    static appendLoader(el) {
        el.insertAdjacentHTML('beforeend', ElementLoadingIndicatorUtil.getTemplate());
    }

}
