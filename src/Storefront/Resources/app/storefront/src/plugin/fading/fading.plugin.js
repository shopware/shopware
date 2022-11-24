import Plugin from 'src/plugin-system/plugin.class';
import DomAccess from 'src/helper/dom-access.helper';
import Debouncer from 'src/helper/debouncer.helper';

export default class FadingPlugin extends Plugin {
    static options = {
        resizeDebounceTime: 200,
    };

    init() {
        const collapses = DomAccess.querySelectorAll(this.el, '.collapse', false);

        if (!collapses.length) {
            return;
        }

        collapses.forEach((collapse) => {
            const containers = DomAccess.querySelectorAll(collapse, '.swag-fade-container', false);

            if (!containers.length) {
                return;
            }

            containers.forEach((container) => {
                const moreLink = DomAccess.querySelector(container.parentNode, '.swag-fading-link-more', false);
                const lessLink = DomAccess.querySelector(container.parentNode, '.swag-fading-link-less', false);

                this._registerEventListeners(collapse, container, moreLink, lessLink);
            });
        });
    }

    /**
     * @returns {void}
     */
    _registerEventListeners(collapse, container, moreLink, lessLink) {
        if ((!moreLink && !lessLink) || !collapse || !container) {
            return;
        }

        window.addEventListener(
            'resize',
            Debouncer.debounce(
                this._onCollapseShow.bind(this, container, moreLink, lessLink),
                this.options.resizeDebounceTime
            )
        );

        collapse.addEventListener('shown.bs.collapse', this._onCollapseShow.bind(this, container, moreLink, lessLink));

        moreLink.addEventListener(
            'click',
            event => this._onLinkClick.call(this, event, moreLink, lessLink, container, 'more')
        );

        lessLink.addEventListener(
            'click',
            event => this._onLinkClick.call(this, event, moreLink, lessLink, container, 'less')
        );
    }

    _onLinkClick(event, moreLink, lessLink, container, action) {
        if (action === 'more') {
            container.classList.add('swag-fade-container-collapsed');
            container.classList.remove('swag-fade-container');

            moreLink.classList.add('swag-fade-link-hidden');
            lessLink.classList.remove('swag-fade-link-hidden');
        } else {
            container.classList.add('swag-fade-container');
            container.classList.remove('swag-fade-container-collapsed');

            lessLink.classList.add('swag-fade-link-hidden');
            moreLink.classList.remove('swag-fade-link-hidden');
        }

        event.preventDefault();
    }

    _onCollapseShow(container, moreLink, lessLink) {
        if (container.scrollHeight === container.offsetHeight) {
            moreLink.classList.add('swag-fade-link-hidden');
            lessLink.classList.add('swag-fade-link-hidden');
        } else {
            container.classList.add('swag-fade-container');
            container.classList.remove('swag-fade-container-collapsed');

            lessLink.classList.add('swag-fade-link-hidden');
            moreLink.classList.remove('swag-fade-link-hidden');
        }
    }
}
