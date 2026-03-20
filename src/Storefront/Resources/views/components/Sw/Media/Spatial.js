export default class MediaSpatial extends ShopwareComponent {
    async init() {
        this.canvas = this.el.querySelector('canvas');

        if (!this.canvas) {
            return;
        }

        const { QuickView } = await import('@shopware-ag/dive/quickview');

        this.dive = await QuickView(this.options.modelUrl, {
            autoStart: false,
            canvas: this.canvas,
        });

        this.dive.start();
    }
}