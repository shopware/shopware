/**
 * @jest-environment jsdom
 */

/* eslint-disable */
import OffCanvasFilter from 'src/plugin/offcanvas-filter/offcanvas-filter.plugin';

describe('Offcanvas filter tests', () => {
    let offcanvasFilter = undefined;
    let mockDomElement = undefined;

    beforeEach(() => {
        window.PluginManager = {
            getPluginInstancesFromElement: () => {
                return new Map();
            },
            getPluginInstances: () => {
                return [
                    {
                        refreshRegistry: () => {}
                    }
                ];
            },
            getPlugin: () => {
                return {
                    get: () => []
                };
            },
            initializePlugins: undefined
        };

        document.$emitter = {
            unsubscribe: () => {},
            subscribe: () => {},
        };

        // mock offcanvas filter plugins
        mockDomElement = document.createElement('div');
        offcanvasFilter = new OffCanvasFilter(mockDomElement);
    });

    afterEach(() => {
        offcanvasFilter = undefined;
        mockDomElement = undefined;
    });

    test('offcanvasFilter plugin exists', () => {
        expect(typeof offcanvasFilter).toBe('object');
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
        targetDomNode.setAttribute('data-offcanvas-filter-content', 'true');
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

        document.body.innerHTML = '';
    });
});
