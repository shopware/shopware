import OffCanvasTabs from 'src/plugin/offcanvas-tabs/offcanvas-tabs.plugin';

describe('OffCanvasTabsPlugin test', () => {
    let offCanvasTabs;

    beforeEach(() => {
        document.body.innerHTML = `
            <ul class="nav nav-tabs">
                <li class="nav-item">
                    <a class="nav-link" href="#tab-pane-content">
                        Tab item
                    </a>
                </li>
            </ul>

            <div id="tab-pane-content">Tab content</div>
        `;

        const element = document.querySelector('.nav-link');

        offCanvasTabs = new OffCanvasTabs(element);
        offCanvasTabs._isInAllowedViewports = () => true;

        window.PluginManager.initializePlugins = jest.fn();

        window.focusHandler = {
            saveFocusState: jest.fn(),
            resumeFocusState: jest.fn(),
        };

        jest.useFakeTimers();
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.useRealTimers();
    });

    test('plugin initializes', () => {
        expect(typeof offCanvasTabs).toBe('object');
        expect(offCanvasTabs).toBeInstanceOf(OffCanvasTabs);
    });

    test('opens offCanvas', () => {
        const button = document.querySelector('.nav-link');

        // Click button
        button.dispatchEvent(new Event('click', { bubbles: true }));

        jest.runAllTimers();

        const offCanvas = document.querySelector('.offcanvas');

        expect(window.PluginManager.initializePlugins).toBeCalledTimes(1);
        expect(offCanvas.innerHTML).toBe('Tab content');
    });
});
