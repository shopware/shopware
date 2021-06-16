import Plugin from 'src/plugin-system/plugin.class';
import Debouncer from 'src/helper/debouncer.helper';
import DeviceDetection from 'src/helper/device-detection.helper';

export default class ScrollUpPlugin extends Plugin {

    static options = {

        /**
         * debounce time for the scroll event
         */
        scrollDebounceTime: 35,

        /**
         * scroll up button selector
         */
        buttonSelector: '.js-scroll-up-button',

        /**
         * scroll up button visible at position
         */
        visiblePos: 250,
        visibleCls: 'is-visible',

    };

    init() {
        this._button = this.el.querySelector(this.options.buttonSelector);
        this._defaultPadding = window.getComputedStyle(this._button).getPropertyValue('bottom');

        this._assignDebouncedOnScrollEvent();
        this._addBodyPadding();
        this._registerEvents();
    }

    /**
     * registers all needed events
     *
     * @private
     */
    _registerEvents() {
        const submitEvent = (DeviceDetection.isTouchDevice()) ? 'touchstart' : 'click';

        if (this._button) {
            this._toggleVisibility();

            this._button.addEventListener(submitEvent, () => {
                this._scrollToTop();

                this.$emitter.publish('onClickButton');
            });
        }

        document.addEventListener('scroll', this._debouncedOnScroll, false);
        const observer = new MutationObserver(this._addBodyPadding.bind(this));
        observer.observe(document.body, {
            attributes: true,
            attributeFilter: ['style'],
        })
    }

    /**
     * debounce is required to ensure the callback gets executed when scrolling ends
     *
     * @return {Function}
     * @private
     */
    _assignDebouncedOnScrollEvent() {
        this._debouncedOnScroll = Debouncer.debounce(this._toggleVisibility.bind(this), this.options.scrollDebounceTime);
    }

    /**
     * scroll to top
     *
     * @private
     */
    _scrollToTop() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth',
        });

        this.$emitter.publish('scrollToTop');
    }

    /**
     * toggle visibility scroll-up button
     *
     * @private
     */
    _toggleVisibility() {
        if (window.scrollY > this.options.visiblePos) {
            this._button.classList.add(this.options.visibleCls);
        } else {
            this._button.classList.remove(this.options.visibleCls);
        }

        this.$emitter.publish('toggleVisibility');
    }

    _addBodyPadding() {
        this._button.style.bottom = `calc(${this._defaultPadding} + ${document.body.style.paddingBottom || '0px'})`;
    }
}
