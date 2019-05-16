import Plugin from 'src/script/helper/plugin/plugin.class';
import HttpClient from 'src/script/service/http-client.service';
import PageLoadingIndicatorUtil from 'src/script/utility/loading-indicator/page-loading-indicator.util';
import PseudoModalUtil from 'src/script/utility/modal-extension/pseudo-modal.util';
import DomAccess from 'src/script/helper/dom-access.helper';
import Iterator from 'src/script/helper/iterator.helper';
import PluginManager from 'src/script/helper/plugin/plugin.manager';

/**
 * this plugins opens a modal
 * where an address can be edited or created
 */
export default class AddressEditorPlugin extends Plugin {

    static options = {
        url: window.router['frontend.account.addressbook'],
        redirectRoute: false,
        replaceSelector: false,
        addressId: false,
        changeShipping: false,
        changeBilling: false,
    };

    init() {
        if (!this.options.changeShipping && !this.options.changeBilling) {
            throw new Error('One or both of the options "changeShipping" or "changeShipping" has to be true!');
        }

        this._client = new HttpClient(window.accessKey, window.contextToken);
        this._registerEvents();
    }

    /**
     * registers all needed event listeners
     *
     * @private
     */
    _registerEvents() {
        const onClick = this._onClick.bind(this);
        this.el.removeEventListener('click', onClick);
        this.el.addEventListener('click', onClick);
    }

    /**
     * callback when element is clicked
     *
     * @param {Event} event
     *
     * @private
     */
    _onClick(event) {
        event.preventDefault();
        PageLoadingIndicatorUtil.create();

        const data = this._getRequestData();

        this._client.abort();
        this._client.post(this.options.url, JSON.stringify(data), content => this._openModal(content));
    }

    /**
     * returns the request data
     *
     * @returns {*}
     *
     * @private
     */
    _getRequestData() {
        return {
            id: this.options.id,
            redirectRoute: this.options.redirectRoute,
            replaceSelector: this.options.replaceSelector,
            changeableAddresses: {
                changeShipping: this.options.changeShipping,
                changeBilling: this.options.changeBilling,
            },
        };
    }

    /**
     * opens the address edit modal with the
     * ajax call content
     *
     * @param {string} response
     *
     * @private
     */
    _openModal(response) {
        const pseudoModal = new PseudoModalUtil(response);
        PageLoadingIndicatorUtil.remove();
        pseudoModal.open(this._onOpen.bind(this, pseudoModal));
    }

    /**
     * callback after the modal is opened
     *
     * @param {PseudoModalUtil} pseudoModal
     *
     * @private
     */
    _onOpen(pseudoModal) {
        const modal = pseudoModal.getModal();
        modal.classList.add('address-editor-modal');
        window.PluginManager.initializePlugins();
        this._registerModalEvents(pseudoModal);
    }

    /**
     * register all needed events
     * after the modal content is set
     *
     * @param {PseudoModalUtil} pseudoModal
     *
     * @private
     */
    _registerModalEvents(pseudoModal) {
        this._registerCollapseCallback(pseudoModal);
        this._registerAjaxSubmitCallback(pseudoModal);
    }

    /**
     * callback to update the modal position
     * after the collapses have changed
     *
     * @param {PseudoModalUtil} pseudoModal
     *
     * @private
     */
    _registerCollapseCallback(pseudoModal) {
        const modal = pseudoModal.getModal();
        const collapseTriggers = DomAccess.querySelectorAll(modal, '[data-toggle="collapse"]', false);
        if (collapseTriggers) {
            Iterator.iterate(collapseTriggers, collapseTrigger => {
                const targetSelector = DomAccess.getDataAttribute(collapseTrigger, 'data-target');
                const target = DomAccess.querySelector(modal, targetSelector);
                const parentSelector = DomAccess.getDataAttribute(target, 'data-parent');
                const parent = DomAccess.querySelector(modal, parentSelector);
                const $parent = $(parent);

                $parent.on('hidden.bs.collapse', () => {
                    pseudoModal.updatePosition();
                });
            });
        }
    }

    /**
     * callback to close the modal after address selection
     * callback to register the modal events after ajax submit
     *
     * @param {PseudoModalUtil} pseudoModal
     *
     * @private
     */
    _registerAjaxSubmitCallback(pseudoModal) {
        const modal = pseudoModal.getModal();
        const ajaxForms = DomAccess.querySelectorAll(modal, '[data-form-ajax-submit]', false);
        if (ajaxForms) {
            Iterator.iterate(ajaxForms, ajaxForm => {

                /** @type FormAjaxSubmitPlugin **/
                const instance = PluginManager.getPluginInstanceFromElement(ajaxForm, 'FormAjaxSubmit');
                if (instance) {
                    const shouldBeClosed = ajaxForm.classList.contains('js-close-address-editor');
                    if (shouldBeClosed) {
                        instance.addCallback(() => {
                            pseudoModal.close();
                        });
                    }

                    instance.addCallback(this._registerModalEvents.bind(this, pseudoModal));
                }
            });
        }
    }

}
