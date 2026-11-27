export default class LayoutSwitch extends ShopwareComponent {

    static options = {
        paramName: 'listingLayout',
    };

    init() {
        this.buttons = this.el.querySelectorAll('[data-layout]');

        this.registerEvents();
    }

    registerEvents() {
        this.buttons.forEach((button) => {
            button.addEventListener('click', this.onChangeLayout.bind(this));
        });
    }

    onChangeLayout(event) {
        const layout = event.currentTarget.getAttribute('data-layout');
        this.changeLayout(layout);
    }

    changeLayout(layout) {
        this.buttons.forEach((button) => {
            const active = button.dataset.layout === layout;
            button.classList.toggle('is--active', active);
            button.setAttribute('aria-pressed', String(active));
        });

        Shopware.emit('LayoutSwitch:Change', this.options.paramName, layout);

        this.dispatchEvent('LayoutSwitch:Change', {
            layout,
        });
    }

    destroy() {
        this.buttons.forEach((button) => {
            button.removeEventListener('click', this.onChangeLayout.bind(this));
        });
    }
}
