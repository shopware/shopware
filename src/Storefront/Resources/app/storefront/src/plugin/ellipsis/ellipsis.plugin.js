import Plugin from 'src/plugin-system/plugin.class';
import DomAccess from 'src/helper/dom-access.helper';

/** @deprecated tag:v6.6.0 - Plugin "Ellipsis" will be removed without replacement. Plugin is not used anymore. */
export default class EllipsisPlugin extends Plugin {
    static options = {
        hiddenClass: 'swag-ellipsis-hidden',
    };

    init() {
        this._registerEventListeners();
        this.ellipsisSpan = DomAccess.querySelector(this.el, '.swag-ellipsis-span', false);
        this.totalSpan = DomAccess.querySelector(this.el, '.swag-ellipsis-total-span', false);

        this.totalSpan.style.display = 'none'
    }

    /**
     * @returns {void}
     */
    _registerEventListeners() {
        const expandLink = DomAccess.querySelector(this.el, '.swag-ellipsis-expand-link', false);
        const shrinkLink = DomAccess.querySelector(this.el, '.swag-ellipsis-shrink-link', false);

        if(!expandLink && !shrinkLink) {
            return;
        }

        expandLink.addEventListener(
            'click',
            event => this._onLinkClick.call(this, event, 'expand')
        );

        shrinkLink.addEventListener(
            'click',
            event => this._onLinkClick.call(this, event, 'shrink')
        );

    }

    _onLinkClick(event, action) {
        this.ellipsisSpan.style.display = action === 'expand' ? 'none' : 'inline';
        this.totalSpan.style.display = action === 'shrink' ? 'none' : 'inline';

        event.preventDefault();
    }
}
