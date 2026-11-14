import Plugin from 'src/plugin-system/plugin.class';
import QRCode from 'qrcode';

/**
 * @package innovation
 *
 * @experimental stableVersion:v6.8.0 feature:SPATIAL_BASES
 */

type PageQrcodeGeneratorOptions = {
    errorCorrectionLevel: string;
    width: number;
    params?: Record<string, string>;
};

export default class PageQrcodeGeneratorPlugin extends Plugin<PageQrcodeGeneratorOptions> {
    public static options: PageQrcodeGeneratorOptions = {
        errorCorrectionLevel: 'H',
        width: 300,
    };

    /**
     * initialize plugin
     */
    public init() {
        const qrLink = new URL(window.location.href);
        if (this.options.params) {
            for (const [key, value] of Object.entries(this.options.params)) {
                qrLink.searchParams.set(key, value);
            }
        }
        // eslint-disable-next-line
        QRCode.toCanvas(qrLink.toString(), this.options, (err, canvas) => {
            if (err) return;
            if (this.options.params?.autostartAr) {
                // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
                canvas.dataset.arModelId = this.options.params.autostartAr;
            }
            this.el.appendChild(canvas);
        });
    }
}
