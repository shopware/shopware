/* eslint-disable import/no-unresolved */
import Plugin from 'src/plugin-system/plugin.class';
import DomAccess from 'src/helper/dom-access.helper';

export default class EllipsisPlugin extends Plugin {
    static options = {
        hiddenClass: 'swag-ellipsis-hidden',
    };

    init() {
        this._registerEventListeners();
    }

    /**
     * @returns {void}
     */
    _registerEventListeners() {
        const expandLink = DomAccess.querySelector(this.el, '.swag-ellipsis-expand-link', false);
        const shrinkLink = DomAccess.querySelector(this.el, '.swag-ellipsis-shrink-link', false);

        expandLink.addEventListener(
            'click',
            this._onLinkClick.bind(this)
        );

        shrinkLink.addEventListener(
            'click',
            this._onLinkClick.bind(this)
        );
    }

    _onLinkClick(event) {
        const ellipsisSpan = DomAccess.querySelector(this.el, '.swag-ellipsis-span', false);
        const totalSpan = DomAccess.querySelector(this.el, '.swag-ellipsis-total-span', false);

        if (!ellipsisSpan.classList.contains(this.options.hiddenClass)) {
            ellipsisSpan.classList.add(this.options.hiddenClass);
            totalSpan.classList.remove(this.options.hiddenClass);
        } else {
            totalSpan.classList.add(this.options.hiddenClass);
            ellipsisSpan.classList.remove(this.options.hiddenClass);
        }

        event.preventDefault();
    }
}
