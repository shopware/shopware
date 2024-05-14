import Plugin from 'src/plugin-system/plugin.class';
import HttpClient from 'src/service/http-client.service';
import ButtonLoadingIndicatorUtil from 'src/utility/loading-indicator/button-loading-indicator.util';
import PageLoadingIndicatorUtil from 'src/utility/loading-indicator/page-loading-indicator.util';
import PseudoModalUtil from 'src/utility/modal-extension/pseudo-modal.util';
import DomAccess from 'src/helper/dom-access.helper';
import Iterator from 'src/helper/iterator.helper';

/**
 * @package checkout
 *
 * this plugins opens a modal
 * where an address can be edited or created
 */
export default class AddressEditorPlugin extends Plugin {

    static options = {
        url: window.router['frontend.account.addressbook'],
        addressId: false,
        changeShipping: false,
        changeBilling: false,
        editorModalClass: 'address-editor-modal',
        closeEditorClass: 'js-close-address-editor',
    };

    init() {
        if (!this.options.changeShipping && !this.options.changeBilling) {
            throw new Error('One or both of the options "changeShipping" or "changeShipping" has to be true!');
        }

        this._client = new HttpClient();
        this._registerEvents();
    }

    /**
     * registers all needed event listeners
     *
     * @private
     */
    _registerEvents() {
        const onClick = this._getModal.bind(this);

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
    _getModal(event) {
        event.preventDefault();

        try {
            this._btnLoader = new ButtonLoadingIndicatorUtil(event.currentTarget);
            this._btnLoader.create();
        } catch (error) {
            console.warn('[AddressEditorPlugin] Unable to create loading indicator on button', error);
        }

        const data = this._getRequestData();

        this.$emitter.publish('beforeGetModal');

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
        const data = {
            id: this.options.addressId,
            changeableAddresses: {
                changeShipping: this.options.changeShipping,
                changeBilling: this.options.changeBilling,
            },
        };

        return data;
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

        pseudoModal.open(this._onOpen.bind(this, pseudoModal), 0);

        const modal = pseudoModal.getModal();

        modal.classList.add(this.options.editorModalClass);
    }

    /**
     * callback after the modal is opened
     *
     * @param {PseudoModalUtil} pseudoModal
     *
     * @private
     */
    _onOpen(pseudoModal) {
        try {
            this._btnLoader.remove();
        } catch (error) {
            console.warn('[AddressEditorPlugin] Unable to remove loading indicator from button', error);
        }

        window.PluginManager.initializePlugins().then(() => {
            this._registerModalEvents(pseudoModal);
        });

        this.$emitter.publish('onOpen', { pseudoModal });
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

        this.$emitter.publish('registerModalEvents', { pseudoModal });
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

        const collapseTriggers = DomAccess.querySelectorAll(modal, '[data-bs-toggle="collapse"]', false);

        if (collapseTriggers) {
            Iterator.iterate(collapseTriggers, collapseTrigger => {
                const targetSelector = DomAccess.getDataAttribute(collapseTrigger, 'data-bs-target');
                const target = DomAccess.querySelector(modal, targetSelector);
                const parentSelector = DomAccess.getDataAttribute(target, 'data-bs-parent');
                const parent = DomAccess.querySelector(modal, parentSelector);

                parent.addEventListener('hidden.bs.collapse', () => {
                    pseudoModal.updatePosition();

                    this.$emitter.publish('collapseHidden', { pseudoModal });
                });
            });
        }

        this.$emitter.publish('registerCollapseCallback', { pseudoModal });
    }

    /**
     * callback to close the modal after address selection success
     * callback to display the validation message nearby the invalid field
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
                const FormAjaxSubmitInstance = window.PluginManager.getPluginInstanceFromElement(ajaxForm, 'FormAjaxSubmit');

                if (FormAjaxSubmitInstance) {
                    FormAjaxSubmitInstance.addCallback(() => {
                        this._registerAjaxSubmitCallback(pseudoModal);

                        const invalidFields = DomAccess.querySelectorAll(
                            modal,
                            `${FormAjaxSubmitInstance.options.replaceSelectors[0]} .is-invalid`,
                            false
                        );

                        if (invalidFields) {
                            return;
                        }

                        const shouldBeClosed = ajaxForm.classList.contains(this.options.closeEditorClass);
                        if (shouldBeClosed) {
                            pseudoModal.close();
                            PageLoadingIndicatorUtil.create();

                            // dirty hack, because chromium cache is weird
                            // basically a window.location.reload() but chrome reloads
                            // with ?redirected=1 which is not the wanted behaviour
                            // this replaces the redirected=1 to a redirected=0
                            if (typeof URL === 'function') {
                                const url = new URL(window.location.href);
                                url.searchParams.delete('redirected');
                                window.location.assign(url.toString());
                            } else {
                                window.location.reload();
                            }
                        }
                    });
                }
            });
        }
        this.$emitter.publish('registerAjaxSubmitCallback', { pseudoModal });
    }
}
