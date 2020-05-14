import Plugin from 'src/plugin-system/plugin.class';
import Iterator from 'src/helper/iterator.helper';
import { clearAllBodyScrollLocks, disableBodyScroll } from 'body-scroll-lock';

const NO_SCROLL_CLS = 'no-scroll';

export default class ScrollLockPlugin extends Plugin {

    static options = {
        scrollable: [
            '.offcanvas',
            '.modal'
        ]
    };

    init() {
        this._registerEvents();
    }

    _registerEvents() {
        this.mutationObserver = new MutationObserver(this._onMutate.bind(this));
        this.mutationObserver.observe(this.el, {
            attributes: true,
            characterData: true,
            childList: false,
            subtree: false,
            attributeOldValue: false,
            characterDataOldValue: false
        });
        $(document).on('show.bs.modal', () => document.documentElement.classList.add(NO_SCROLL_CLS));
        $(document).on('hide.bs.modal', () => document.documentElement.classList.remove(NO_SCROLL_CLS));
    }

    /**
     * listen to the class changes of the provided element
     * if no-scroll is present body scroll is disabled
     *
     * @param records
     * @private
     */
    _onMutate(records) {
        Iterator.iterate(records, record => {
            if (record.type === 'attributes' && record.attributeName === 'class') {
                if (record.target.classList.contains(NO_SCROLL_CLS)) {
                    this._disableScroll();
                } else {
                    this._enableScroll();
                }
            }
        })
    }

    /**
     * disables scroll expect on the elements listed in options.scrollable
     *
     * @private
     */
    _disableScroll() {
        Iterator.iterate(this.options.scrollable, scrollableSelector => {
            const scrollables = document.querySelectorAll(scrollableSelector);
            Iterator.iterate(scrollables, scrollable => {
                scrollable.style['-webkit-overflow-scrolling'] = 'touch';
                scrollable.style['scroll-behavior'] = 'smooth';
                disableBodyScroll(scrollable);
            });
        });
    }

    /**
     * enables the scroll again
     *
     * @private
     */
    _enableScroll() {
        clearAllBodyScrollLocks();
        Iterator.iterate(this.options.scrollable, scrollableSelector => {
            const scrollables = document.querySelectorAll(scrollableSelector);
            Iterator.iterate(scrollables, scrollable => {
                scrollable.style['-webkit-overflow-scrolling'] = 'initial';
                scrollable.style['scroll-behavior'] = 'initial';
            });
        });
    }
}
