export default class ScrollNavigation extends ShopwareComponent {

    static options = {
        activeClass: 'is--active',
        // Fraction of the viewport height from the top at which a target counts as the current section.
        activationLine: 0.35,
    };

    init() {
        this.links = Array.from(this.el.querySelectorAll('a[data-scroll-navigation-target]'));
        this.targets = this.links
            .map((link) => document.getElementById(link.dataset.scrollNavigationTarget))
            .filter((target) => target !== null);

        if (this.targets.length === 0) {
            return;
        }

        // Browsers that implement scroll-target-group mark the current link via :target-current themselves.
        if (typeof CSS !== 'undefined' && CSS.supports?.('selector(:target-current)')) {
            return;
        }

        this.onScroll = this.updateActiveLink.bind(this);
        window.addEventListener('scroll', this.onScroll, { passive: true });
        window.addEventListener('resize', this.onScroll, { passive: true });

        this.updateActiveLink();
    }

    updateActiveLink() {
        const activationLine = window.innerHeight * this.options.activationLine;
        let current = null;

        this.targets.forEach((target) => {
            if (target.getBoundingClientRect().top <= activationLine) {
                current = target;
            }
        });

        if (current === null) {
            current = this.targets[0];
        }

        this.links.forEach((link) => {
            const isActive = link.dataset.scrollNavigationTarget === current.id;

            link.classList.toggle(this.options.activeClass, isActive);

            if (isActive) {
                link.setAttribute('aria-current', 'true');
            } else {
                link.removeAttribute('aria-current');
            }
        });
    }

    destroy() {
        if (!this.onScroll) {
            return;
        }

        window.removeEventListener('scroll', this.onScroll);
        window.removeEventListener('resize', this.onScroll);
    }
}
