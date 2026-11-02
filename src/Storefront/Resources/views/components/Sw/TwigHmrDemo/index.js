/**
 * DEMO: counts the clicks, so a test can tell a kept instance from a new one. Remove before merge.
 */
export default class TwigHmrDemo extends window.ShopwareComponent {
    init() {
        this.count = 0;
        this.button = this.el.querySelector('button');
        this.output = this.el.querySelector('.twig-hmr-demo-count');
        this.button.addEventListener('click', () => {
            this.count += 1;
            this.output.textContent = String(this.count);
        });
        console.info('[twig-hmr-demo] init', this.button.textContent);
    }

    destroy() {
        console.info('[twig-hmr-demo] destroy', this.button.textContent);
    }
}
