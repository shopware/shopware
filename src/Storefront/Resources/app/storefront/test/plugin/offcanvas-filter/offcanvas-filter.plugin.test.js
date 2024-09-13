/* eslint-disable */
import OffCanvasFilter from 'src/plugin/offcanvas-filter/offcanvas-filter.plugin';
import OffCanvas from 'src/plugin/offcanvas/offcanvas.plugin';
import Feature from 'src/helper/feature.helper';

describe('Offcanvas filter tests', () => {
    let offcanvasFilter = undefined;
    let mockDomElement = undefined;

    beforeEach(() => {
        window.PluginManager.getPluginInstances = () => {
            return [
                {
                    refreshRegistry: () => {}
                }
            ];
        }

        document.$emitter = {
            unsubscribe: () => {},
            subscribe: () => {},
        };

        window.focusHandler = {
            saveFocusState: jest.fn(),
            resumeFocusState: jest.fn(),
        };

        // mock offcanvas filter plugins
        mockDomElement = document.createElement('div');
        offcanvasFilter = new OffCanvasFilter(mockDomElement);
    });

    afterEach(() => {
        document.body.innerHTML = '';
        offcanvasFilter = undefined;
        mockDomElement = undefined;
    });

    test('offcanvasFilter plugin exists', () => {
        expect(typeof offcanvasFilter).toBe('object');
    });

    test('opens offCanvas with content from data-attribute', () => {
        jest.useFakeTimers();

        document.body.innerHTML = `
            <div data-off-canvas-filter-content="true">
                <div class="filter-panel">I will be moved to the OffCanvas</div>
            </div>
        `;

        // Open offCanvas filter and wait until opened
        mockDomElement.click();
        jest.advanceTimersByTime(OffCanvas.REMOVE_OFF_CANVAS_DELAY);

        // Verify filter offCanvas exists and is shown
        expect(document.querySelector('.offcanvas.offcanvas-filter.show')).toBeTruthy();

        // Verify filter-panel was moved inside OffCanvas
        expect(document.querySelector('.offcanvas.offcanvas-filter .filter-panel').textContent).toBe('I will be moved to the OffCanvas');

        // Verify filter-panel was removed from the inside data-attribute
        expect(document.querySelector('[data-offcanvas-filter-content] .filter-panel')).toBe(null);

        jest.useRealTimers();
    });

    test('_onClickOffCanvasFilter get called on click', () => {
        const shouldBeClicked = jest.fn();

        // Mock the function which should be called on click
        jest.spyOn(OffCanvasFilter.prototype, '_onClickOffCanvasFilter').mockImplementation(shouldBeClicked);

        // create an mock offcanvas filter
        const mockClickableDomElement = document.createElement('div');
        new OffCanvasFilter(mockClickableDomElement);

        // simulate click
        mockClickableDomElement.click();

        expect(shouldBeClicked).toHaveBeenCalled();

        // Reset mock
        OffCanvasFilter.prototype._onClickOffCanvasFilter.mockRestore();
    });

    test('_onCloseOffCanvas replaces the dom innerHTML', () => {
        const sourceDomNode = document.createElement('div');
        sourceDomNode.appendChild(document.createElement('h1'));
        sourceDomNode.setAttribute('id', 'itWorksReallyGood');

        const targetDomNode = document.createElement('div');
        targetDomNode.setAttribute('data-off-canvas-filter-content', 'true');
        document.body.appendChild(targetDomNode);

        const mockEvent = {
            detail: {
                offCanvasContent: [
                    sourceDomNode
                ]
            }
        };

        expect(targetDomNode.innerHTML).toBe('');

        offcanvasFilter._onCloseOffCanvas(mockEvent);

        expect(targetDomNode.innerHTML).toBe('<h1></h1>');
    });
});
