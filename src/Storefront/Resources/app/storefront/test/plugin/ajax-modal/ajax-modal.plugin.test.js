import AjaxModalPlugin from 'src/plugin/ajax-modal/ajax-modal.plugin';
import PseudoModalUtil from 'src/utility/modal-extension/pseudo-modal.util';
import DomAccess from 'src/helper/dom-access.helper';
import LoadingIndicatorUtil from 'src/utility/loading-indicator/loading-indicator.util';

/**
 * @package storefront
 */
describe('AjaxModalPlugin tests', () => {
    let ajaxModalPlugin = undefined;

    beforeEach(() => {
        window.PluginManager.initializePlugins = jest.fn();

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

        expect(window.PluginManager.initializePlugins).toBeCalled();

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
        const event = new Event('foo', { cancelable: true });

        event.preventDefault = jest.fn();
        event.stopPropagation = jest.fn();

        const element = document.createElement('div');

        ajaxModalPlugin._openModal = jest.fn();
        ajaxModalPlugin._loadModalContent = jest.fn();

        ajaxModalPlugin.options.centerLoadingIndicatorClass = 'foo';

        // Mock the DomAccess.querySelector method only in this test case
        const mockDomAccess = jest.spyOn(DomAccess, 'querySelector');
        mockDomAccess.mockImplementation(() => {
            return element;
        });

        ajaxModalPlugin._onClickHandleAjaxModal(event);

        expect(element.classList).toContain('foo');

        expect(event.preventDefault).toBeCalled();
        expect(event.stopPropagation).toBeCalled();
        expect(ajaxModalPlugin._openModal).toBeCalled();
        expect(ajaxModalPlugin._loadModalContent).toBeCalled();

        mockDomAccess.mockRestore();
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
        expect(pseudoModalUtil.updateContent).toBeCalledWith(response, expect.any(Function));
        expect(element.classList).not.toContain('text-center');
    });

    test('renders back button when previous modal url is set', () => {
        document.body.innerHTML = `
            <!-- This is the modal trigger -->
            <a
                data-ajax-modal="true"
                data-url="/widgets/cms/contact-form-id"
                data-prev-url="/widgets/cms/prev-id"
                href="/widgets/cms/contact-form-id"
            >
                Open ajax modal
            </a>

            <div class="js-pseudo-modal-template">
                <div class="modal modal-lg fade" tabindex="-1" role="dialog">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header only-close">
                                <div class="modal-title js-pseudo-modal-template-title-element h5"></div>
                                <button type="button" class="btn-close close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body js-pseudo-modal-template-content-element">
                            </div>
                        </div>
                    </div>
                    <template class="js-pseudo-modal-back-btn-template">
                        <button class="js-pseudo-modal-back-btn btn btn-outline-primary" data-ajax-modal="true" data-url="#" href="#">
                           Back
                        </button>
                    </template>
                </div>
            </div>
        `;

        // Overwrite the ajax modal instance to consider additional attributes
        ajaxModalPlugin = new AjaxModalPlugin(document.querySelector('[data-ajax-modal]'));

        const response = '<div class="cms-page">Contact form content</div>';
        const loadingIndicatorUtil = new LoadingIndicatorUtil(document.createElement('div'));
        loadingIndicatorUtil.remove = jest.fn();

        const pseudoModalUtil = new PseudoModalUtil();
        pseudoModalUtil.updateContent = jest.fn((response, callback) => callback());
        pseudoModalUtil._modal = document.querySelector('.modal');

        ajaxModalPlugin._processResponse(
            response,
            loadingIndicatorUtil,
            pseudoModalUtil,
            document.querySelector('.js-pseudo-modal-template-content-element')
        );

        // Verify back button is rendered at correct location using the <template> as boilerplate
        const renderedBackButton = document.querySelector('.js-pseudo-modal-template-content-element .js-pseudo-modal-back-btn');
        expect(renderedBackButton.getAttribute('href')).toBe('/widgets/cms/prev-id');
        expect(renderedBackButton.getAttribute('data-url')).toBe('/widgets/cms/prev-id');
    });
});
