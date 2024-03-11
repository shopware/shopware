import ZoomModalPlugin from 'src/plugin/zoom-modal/zoom-modal.plugin';

/**
 * @package storefront
 */
describe('ZoomModalPlugin tests', () => {
    let zoomModalPlugin = undefined;

    beforeEach(() => {
        document.body.innerHTML = `
            <div data-zoom-modal="true">
                <div class="js-zoom-modal">
                    <div class="modal">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-body">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        const element = document.querySelector('[data-zoom-modal]');

        zoomModalPlugin = new ZoomModalPlugin(element);
    });

    afterEach(() => {
        zoomModalPlugin = undefined;
    });

    test('zoom modal plugin exists', () => {
        expect(typeof zoomModalPlugin).toBe('object');
    });

    test('zoom modal show modal has only one listener', () => {
        zoomModalPlugin.$emitter.publish = jest.fn();
        window.PluginManager.register = jest.fn();
        window.PluginManager.initializePlugin = jest.fn();

        const modal = document.querySelector('.js-zoom-modal');

        zoomModalPlugin._showModal(modal);
        zoomModalPlugin._showModal(modal);

        expect(zoomModalPlugin.$emitter.publish).toHaveBeenCalledWith('modalShow', { modal });
        expect(zoomModalPlugin.$emitter.publish).toHaveBeenCalledTimes(2);
    });
});
