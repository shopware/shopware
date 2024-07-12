import PageQrcodeGeneratorPlugin from 'src/plugin/qrcode/page-qrcode-generator';
import QRCode from 'qrcode';
jest.mock('qrcode');

/**
 * @package innovation
 */

describe('PageQrcodeGeneratorPlugin', () => {
    let PageQrcodeGeneratorPluginObject = undefined;
    let toCanvasSpy = undefined;

    beforeEach(() => {
        jest.clearAllMocks();

        document.body.innerHTML = `
            <div data-page-qrcode-generator data-page-qrcode-generator-options='{"params": { "autostartAr": 1}}'></div>
        `;
        toCanvasSpy = jest.spyOn(QRCode, 'toCanvas').mockImplementation((text, options, cb) => {
            cb(null, document.createElement('canvas'));
        });
        PageQrcodeGeneratorPluginObject = new PageQrcodeGeneratorPlugin(document.querySelector('[data-page-qrcode-generator]'), {
            params: {
                autostartAr: 1
            }
        });
    });

    afterEach(() => {
        jest.clearAllMocks();
    });

    test('PageQrcodeGeneratorPlugin is instantiated', () => {
        expect(PageQrcodeGeneratorPluginObject instanceof PageQrcodeGeneratorPlugin).toBe(true);
    });

    test('should have called QRCode toCanvas function', () => {
        expect(toCanvasSpy).toHaveBeenCalled();
    });
});
