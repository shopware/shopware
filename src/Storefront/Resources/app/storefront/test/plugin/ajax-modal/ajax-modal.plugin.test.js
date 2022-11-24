import AjaxModalPlugin from 'src/plugin/ajax-modal/ajax-modal.plugin';
import PseudoModalUtil from 'src/utility/modal-extension/pseudo-modal.util';
import PluginManager from 'src/plugin-system/plugin.manager';
import DomAccess from 'src/helper/dom-access.helper';
import LoadingIndicatorUtil from 'src/utility/loading-indicator/loading-indicator.util';

// Todo: NEXT-23270 - Remove mock ES module import of PluginManager
jest.mock('src/plugin-system/plugin.manager', () => ({
    __esModule: true,
    default: {
        initializePlugins: jest.fn(),
    },
}));

/**
 * @package storefront
 */
describe('AjaxModalPlugin tests', () => {
    let ajaxModalPlugin = undefined;

    beforeEach(() => {
        const mockElement = document.createElement('div');

        // init ajax modal plugins
        ajaxModalPlugin = new AjaxModalPlugin(mockElement);
    });

    afterEach(() => {
        ajaxModalPlugin = undefined;
    });

    test('ajax modal plugin exists', () => {
        expect(typeof ajaxModalPlugin).toBe('object');
    });

    test('_registerEvents is executed properly', () => {
        ajaxModalPlugin.el.removeEventListener = jest.fn();
        ajaxModalPlugin.el.addEventListener = jest.fn();

        ajaxModalPlugin._registerEvents();

        expect(ajaxModalPlugin.el.removeEventListener.mock.calls).toEqual([
            [ 'click', expect.anything() ],
            [ 'touchend', expect.anything() ],
        ]);

        expect(ajaxModalPlugin.el.addEventListener).toBeCalledWith('click', expect.anything());
    });

    test('_onModalOpen will add classes and fire an event', () => {
        const classes = ['foo', 'bar'];
        const element = document.createElement('div');
        const pseudoModalUtil = new PseudoModalUtil();

        pseudoModalUtil.getModal = jest.fn(() => element);
        ajaxModalPlugin.$emitter.publish = jest.fn();

        ajaxModalPlugin._onModalOpen(pseudoModalUtil, classes);

        expect(pseudoModalUtil.getModal).toBeCalled();

        expect(element.classList).toContain('foo');
        expect(element.classList).toContain('bar');

        expect(PluginManager.initializePlugins).toBeCalled();

        expect(ajaxModalPlugin.$emitter.publish).toBeCalledWith('ajaxModalOpen', { modal: element });
    });

    test('_openModal will fetch classes and execute _onModalOpen ', () => {
        const pseudoModalUtil = new PseudoModalUtil();
        pseudoModalUtil.open = jest.fn();

        const element = document.createElement('div');
        element.setAttribute('data-modal-class', 'foo');

        ajaxModalPlugin._onModalOpen.bind = jest.fn();
        ajaxModalPlugin.options.modalClass = 'bar';
        ajaxModalPlugin.el = element;
        ajaxModalPlugin._openModal(pseudoModalUtil);

        expect(pseudoModalUtil.open).toBeCalled();
        expect(ajaxModalPlugin._onModalOpen.bind).toBeCalledWith(ajaxModalPlugin, pseudoModalUtil, ['foo', 'bar']);
    });

    test('_onClickHandleAjaxModal will create a modal window and load its content', () => {
        const event = new Event('foo');

        event.preventDefault = jest.fn();
        event.stopPropagation = jest.fn();

        const element = document.createElement('div');

        ajaxModalPlugin._openModal = jest.fn();
        ajaxModalPlugin._loadModalContent = jest.fn();

        ajaxModalPlugin.options.centerLoadingIndicatorClass = 'foo';

        DomAccess.querySelector = jest.fn(() => {
            return element;
        });

        ajaxModalPlugin._onClickHandleAjaxModal(event);

        expect(element.classList).toContain('foo');

        expect(event.preventDefault).toBeCalled();
        expect(event.stopPropagation).toBeCalled();
        expect(ajaxModalPlugin._openModal).toBeCalled();
        expect(ajaxModalPlugin._loadModalContent).toBeCalled();
    });

    test('_loadModalContent will create a loading indicator and load the actual request', () => {
        const pseudoModalUtil = new PseudoModalUtil();

        const element = document.createElement('div');
        element.setAttribute('data-url', 'foo');

        ajaxModalPlugin.el = element;
        ajaxModalPlugin._processResponse = jest.fn();
        ajaxModalPlugin.httpClient.get = jest.fn((url, callback) => {
            callback();
        });

        ajaxModalPlugin._loadModalContent(pseudoModalUtil, element);

        expect(ajaxModalPlugin.httpClient.get).toBeCalled();
        expect(ajaxModalPlugin._processResponse).toBeCalled();
        expect(element.classList).toContain('text-center');
    });

    test('_processResponse will remove the loading indicator, remove loading classes and update the modal content', () => {
        const response = new XMLHttpRequest();

        const loadingIndicatorUtil = new LoadingIndicatorUtil(document.createElement('div'));
        loadingIndicatorUtil.remove = jest.fn();

        const pseudoModalUtil = new PseudoModalUtil();
        pseudoModalUtil.updateContent = jest.fn();

        const element = document.createElement('div');
        element.classList.add('text-center');

        ajaxModalPlugin._processResponse(response, loadingIndicatorUtil, pseudoModalUtil, element);

        expect(loadingIndicatorUtil.remove).toBeCalled();
        expect(pseudoModalUtil.updateContent).toBeCalledWith(response);
        expect(element.classList).not.toContain('text-center');
    });
});
