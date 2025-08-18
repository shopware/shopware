export default class SwButton extends HTMLElement {
    constructor() {
        super();

        this.variants = {
            variant: {
                primary: 'btn-primary',
                secondary: 'btn-primary',
                light: 'btn-light',
                danger: 'btn-danger',
                warning: 'btn-warning',
            },
        };

        this.elementType = this.getAttribute('element-type') ?? 'button';
        this.href = this.getAttribute('href');
        this.type = this.getAttribute('type');
        this.variant = this.getAttribute('variant');
        this.ariaLabel = this.getAttribute('aria-label');

        this.attachShadow({ mode: 'open' });
        this.shadowRoot.innerHTML = this.template();
    }

    /**
     * This is a more "simple" version that creates the template every time directly
     * when the web-component is created. The downside is that the template is created
     * from scratch for every component instance. The upside is that I have already
     * access to the dynamic content and can use it directly in the template via a simple ${someVar}
     *
     * @returns string
     */
    template() {
        return `
            <link href="${window.themeWebComponentsPath}/vendor/bootstrap.min.css" rel="stylesheet">

            <style>
                /* Fix to make parent grid class work again */
                :host-context(.d-grid) {
                    display: grid !important; 
                }

                .btn-primary {
                    --bs-btn-color: var(--bs-white);
                    --bs-btn-bg: var(--bs-primary);
                    --bs-btn-border-color: var(--bs-primary);
                    --bs-btn-hover-color: var(--bs-white);
                    --bs-btn-hover-bg: #003888;
                    --bs-btn-hover-border-color: #003580;
                    --bs-btn-focus-shadow-rgb: 38, 94, 174;
                    --bs-btn-active-color: var(--bs-white);
                    --bs-btn-active-bg: #003580;
                    --bs-btn-active-border-color: #003278;
                    --bs-btn-active-shadow: inset 0 3px 5px rgba(0, 0, 0, 0.125);
                    --bs-btn-disabled-color: var(--bs-white);
                    --bs-btn-disabled-bg: var(--bs-primary);
                    --bs-btn-disabled-border-color: var(--bs-primary);
                }
            </style>

            <${this.elementType}
                class="sw-button btn ${this.variants.variant[this.variant]}"
                part="button"
                ${(this.href ? `href=${this.href}` : '') }
                ${(this.type ? `type=${this.type}` : '') }
                ${(this.ariaLabel ? `type=${this.ariaLabel}` : '') }
            >
                <slot></slot>
            </${this.elementType}>
        `;
    }
}
