import Plugin from 'src/plugin-system/plugin.class';
import QRCode from 'qrcode';

/**
 * @package innovation
 *
 * @experimental stableVersion:v6.7.0 feature:SPATIAL_BASES
 */

export default class PageQrcodeGeneratorPlugin extends Plugin {
    private el: HTMLElement;

    public static options = {
        errorCorrectionLevel: 'H',
        width: 256,
    };

    /**
     * initialize plugin
     */
    public init() {
        const qrLink = new URL(window.location.href);
        // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
        if (this.options.params) {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
            for (const key in this.options.params) {
                // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-argument
                qrLink.searchParams.set(key, this.options.params[key]);
            }
        }
        // eslint-disable-next-line
        QRCode.toCanvas(qrLink.toString(), this.options, (err, canvas) => {
            if (err) return;
            // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
            if (this.options.params?.autostartAr) {
                // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-assignment
                canvas.dataset.arModelId = this.options.params.autostartAr;
            }
            this.el.appendChild(canvas);
        });
    }
}
