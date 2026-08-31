export default class VariantSelection extends ShopwareComponent {
    static options = {
        focusHandlerKey: "product-variant-selection",
    };

    init() {
        if (this.el.nodeName.toLowerCase() !== "form") {
            throw new Error(
                "This component can only be applied to a form element!",
            );
        }

        this.onChange = this.handleChange.bind(this);
        this.el.addEventListener("change", this.onChange);

        if (window.focusHandler) {
            window.focusHandler.resumeFocusStatePersistent(
                this.options.focusHandlerKey,
            );
        }
    }

    destroy() {
        this.el.removeEventListener("change", this.onChange);
    }

    serialize() {
        const values = {};
        this.el.querySelectorAll("input, select").forEach((field) => {
            if (!field.name || field.disabled) {
                return;
            }
            if (field.type === "radio" && !field.checked) {
                return;
            }
            values[field.name] = field.value;
        });
        return values;
    }

    handleChange(event) {
        if (!this.options.url) {
            return;
        }

        if (window.focusHandler) {
            window.focusHandler.saveFocusStatePersistent(
                this.options.focusHandlerKey,
                `[id="${event.target.id}"]`,
            );
        }

        const query = new URLSearchParams({
            switched: event.target.name,
            options: JSON.stringify(this.serialize()),
        });
        const url = `${this.options.url}?${query.toString()}`;

        window
            .fetch(url, { headers: { "X-Requested-With": "XMLHttpRequest" } })
            .then((response) => response.json())
            .then((response) => this.redirectToVariant(response.url));
    }

    redirectToVariant(url) {
        window.location.replace(url);
    }
}
