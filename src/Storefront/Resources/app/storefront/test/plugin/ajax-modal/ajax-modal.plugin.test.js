import AjaxModalPlugin from 'src/plugin/ajax-modal/ajax-modal.plugin';
import PseudoModalUtil from 'src/utility/modal-extension/pseudo-modal.util';
import LoadingIndicatorUtil from 'src/utility/loading-indicator/loading-indicator.util';

/**
 * @package storefront
 */
describe('AjaxModalPlugin tests', () => {
    let ajaxModalPlugin = undefined;

    beforeEach(() => {
        window.PluginManager.initializePluginsInParentElement = jest.fn();
        window.focusHandler = {
            saveFocusState: jest.fn(),
            resumeFocusState: jest.fn(),
        };

        const mockElement = document.createElement('div');

        document.body.insertAdjacentElement = jest.fn((position, el) => {
            document.body.appendChild(el);
        });

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

        expect(ajaxModalPlugin.el.addEventListener).toHaveBeenCalledWith('click', expect.anything());
    });

    test('_onModalOpen will add classes and fire an event', () => {
        const classes = ['foo', 'bar'];
        const element = document.createElement('div');
        const pseudoModalUtil = new PseudoModalUtil();

        pseudoModalUtil.getModal = jest.fn(() => element);
        ajaxModalPlugin.$emitter.publish = jest.fn();

        ajaxModalPlugin._onModalOpen(pseudoModalUtil, classes);

        expect(pseudoModalUtil.getModal).toHaveBeenCalled();

        expect(element.classList).toContain('foo');
        expect(element.classList).toContain('bar');

        expect(window.PluginManager.initializePluginsInParentElement).toHaveBeenCalled();

        expect(ajaxModalPlugin.$emitter.publish).toHaveBeenCalledWith('ajaxModalOpen', { modal: element });
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

        expect(pseudoModalUtil.open).toHaveBeenCalled();
        expect(ajaxModalPlugin._onModalOpen.bind).toHaveBeenCalledWith(ajaxModalPlugin, pseudoModalUtil, ['foo', 'bar']);
    });

    test('_onClickHandleAjaxModal will create a modal window and load its content', () => {
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
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header only-close">
                                <div class="modal-title js-pseudo-modal-template-title-element h5">Modal Title</div>
                                <button type="button" class="btn-close close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body js-pseudo-modal-template-content-element">Modal Content</div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        const event = new Event('foo', { cancelable: true });

        event.preventDefault = jest.fn();
        event.stopPropagation = jest.fn();

        ajaxModalPlugin = new AjaxModalPlugin(document.querySelector('[data-ajax-modal]'));

        ajaxModalPlugin._openModal = jest.fn();
        ajaxModalPlugin._loadModalContent = jest.fn();

        ajaxModalPlugin.options.centerLoadingIndicatorClass = 'foo';

        ajaxModalPlugin._onClickHandleAjaxModal(event);

        const pseudoModal = document.querySelector('.js-pseudo-modal');
        const modalBody = pseudoModal.querySelector('.modal-body');

        expect(modalBody.classList).toContain('foo');

        expect(event.preventDefault).toHaveBeenCalled();
        expect(event.stopPropagation).toHaveBeenCalled();
        expect(ajaxModalPlugin._openModal).toHaveBeenCalled();
        expect(ajaxModalPlugin._loadModalContent).toHaveBeenCalled();
    });

    test('_loadModalContent will create a loading indicator and load the actual request', async () => {
        const pseudoModalUtil = new PseudoModalUtil();

        const element = document.createElement('div');
        element.setAttribute('data-url', 'foo');

        ajaxModalPlugin.el = element;
        ajaxModalPlugin._processResponse = jest.fn();

        global.fetch = jest.fn(() =>
            Promise.resolve({
                ok: true,
                text: () => Promise.resolve('Some text'),
            })
        );

        await ajaxModalPlugin._loadModalContent(pseudoModalUtil, element);
        await new Promise(process.nextTick);

        expect(ajaxModalPlugin._processResponse).toHaveBeenCalled();
        expect(element.classList).toContain('text-center');
    });

    test('_loadModalContent will not render the response body of a failed request', async () => {
        const element = document.createElement('div');
        element.setAttribute('data-url', 'foo');

        ajaxModalPlugin.el = element;
        ajaxModalPlugin._processResponse = jest.fn();
        ajaxModalPlugin._processError = jest.fn();

        global.fetch = jest.fn(() =>
            Promise.resolve({
                ok: false,
                status: 404,
                text: () => Promise.resolve('<html>Error page</html>'),
            })
        );

        ajaxModalPlugin._loadModalContent(new PseudoModalUtil(), element);
        await new Promise(process.nextTick);

        expect(ajaxModalPlugin._processResponse).not.toHaveBeenCalled();
        expect(ajaxModalPlugin._processError).toHaveBeenCalledTimes(1);
    });

    test('_loadModalContent will handle a network error', async () => {
        const element = document.createElement('div');
        element.setAttribute('data-url', 'foo');

        ajaxModalPlugin.el = element;
        ajaxModalPlugin._processResponse = jest.fn();
        ajaxModalPlugin._processError = jest.fn();

        global.fetch = jest.fn(() => Promise.reject(new TypeError('Failed to fetch')));

        ajaxModalPlugin._loadModalContent(new PseudoModalUtil(), element);
        await new Promise(process.nextTick);

        expect(ajaxModalPlugin._processResponse).not.toHaveBeenCalled();
        expect(ajaxModalPlugin._processError).toHaveBeenCalledTimes(1);
    });

    test('_processError will replace the loading indicator with the error message', () => {
        const errorTemplate = document.createElement('template');
        errorTemplate.classList.add('js-pseudo-modal-error-template');
        errorTemplate.innerHTML = '<div class="alert">Error</div>';
        document.body.appendChild(errorTemplate);

        const loadingIndicatorUtil = { remove: jest.fn() };
        const pseudoModalUtil = { updateContent: jest.fn() };
        const modalBodyEl = document.createElement('div');
        modalBodyEl.classList.add('text-center');

        ajaxModalPlugin._processError(loadingIndicatorUtil, pseudoModalUtil, modalBodyEl);

        expect(loadingIndicatorUtil.remove).toHaveBeenCalledTimes(1);
        expect(pseudoModalUtil.updateContent).toHaveBeenCalledWith('<div class="alert">Error</div>');
        expect(modalBodyEl.classList).not.toContain('text-center');

        errorTemplate.remove();
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

        expect(loadingIndicatorUtil.remove).toHaveBeenCalled();
        expect(pseudoModalUtil.updateContent).toHaveBeenCalledWith(response, expect.any(Function));
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
