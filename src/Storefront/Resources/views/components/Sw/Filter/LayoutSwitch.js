({ Shopware, ShopwareComponent } = window);

class LayoutSwitch extends ShopwareComponent {
    static options = {
        paramName: 'layout',
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
            button.classList.remove('is--active');
        });

        event.currentTarget.classList.add('is--active');

        Shopware.emit(`${this.componentName}:Change`, this.options.paramName, layout);

        this.dispatchEvent(`${this.componentName}:Change`, {
            layout,
        });
    }
}

Shopware.registerComponent('LayoutSwitch', LayoutSwitch);