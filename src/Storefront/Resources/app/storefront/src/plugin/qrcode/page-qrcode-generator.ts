import Plugin from 'src/plugin-system/plugin.class';
import QRCode from 'qrcode';


/**
 * @package innovation
 *
 * @experimental stableVersion:v6.7.0 feature:SPATIAL_BASES
 */

export default class PageQrcodeGeneratorPlugin extends Plugin {

    static options = {
        errorCorrectionLevel: 'H',
        width: 256,
    };

    /**
     * initialize plugin
     */
    public init() {
        const qrContainer = this.el;
        if (qrContainer) {
            // eslint-disable-next-line
            QRCode.toCanvas(window.location.href, this.options, function (err, canvas) {
                if (err) throw err;
                qrContainer.appendChild(canvas);
            })
        }
    }
}
