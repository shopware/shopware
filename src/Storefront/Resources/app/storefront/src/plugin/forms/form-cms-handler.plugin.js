import Plugin from 'src/plugin-system/plugin.class';
import HttpClient from 'src/service/http-client.service';
import PseudoModalUtil from 'src/utility/modal-extension/pseudo-modal.util';
import PageLoadingIndicatorUtil from 'src/utility/loading-indicator/page-loading-indicator.util';
import DomAccess from 'src/helper/dom-access.helper';

export default class FormCmsHandler extends Plugin {

    static options = {
        hiddenClass: 'd-none',
        hiddenSubmitSelector: '.submit--hidden',
        formContentSelector: '.form-content',
        cmsBlock: '.cms-block',
        contentType:  'application/x-www-form-urlencoded',
        privacyNoticeSelector: '.privacy-notice'
    };

    init() {
        this._client = new HttpClient();
        this._getButton();
        this._getPrivacyNoticeLink();
        this._getHiddenSubmit();
        this._registerEvents();
        this._getCmsBlock();
        this._getConfirmationText();
    }

    _registerEvents() {
        this.el.addEventListener('submit', this._handleSubmit.bind(this));

        if (this._button) {
            this._button.addEventListener('submit', this._handleSubmit.bind(this));
            this._button.addEventListener('click', this._handleSubmit.bind(this));
        }

        if (this._privacyNoticeLink) {
            this._privacyNoticeLink.addEventListener('click', this._handlePrivacyNoticeClick.bind(this));
        }
    }

    _getConfirmationText() {
        const input = this.el.querySelector('input[name="confirmationText"]');
        if(input) {
            this._confirmationText = input.value;
        }
    }

    _getPrivacyNoticeLink() {
        this._privacyNoticeLink = this.el.querySelector(this.options.privacyNoticeSelector +' a');
    }

    _getButton() {
        this._button = this.el.querySelector('button');
    }

    _getCmsBlock() {
        this._block = this.el.closest(this.options.cmsBlock);
    }

    _getHiddenSubmit() {
        this._hiddenSubmit = this.el.querySelector(this.options.hiddenSubmitSelector);
    }

    _handlePrivacyNoticeClick(event) {
        event.stopPropagation();
        event.preventDefault();

        const url = DomAccess.getAttribute(event.currentTarget, 'data-url');
        this._client.get(url, response => this._openModalPrivacyNotice(response));
    }

    _openModalPrivacyNotice(response) {
        const pseudoModal = new PseudoModalUtil(response);
        PageLoadingIndicatorUtil.remove();
        pseudoModal.open();
    }

    _handleSubmit(event) {
        event.preventDefault();

        if (this.el.checkValidity()) {
            this._submitForm();
        } else {
            this._showValidation();
        }
    }

    _showValidation() {
        this._hiddenSubmit.click();
    }

    _submitForm() {
        const { _client, el, options } = this;
        const _data = new FormData(el);

        _client.post(el.action, _data, this._handleResponse.bind(this), options.contentType);
    }

    _handleResponse(res) {
        const response = JSON.parse(res);

        if(response.length > 0) {
            let changeContent = true;
            let content = '';
            for (let i = 0; i < response.length; i++) {
                if (response[i]['type'] === 'danger') {
                    changeContent = false;
                }
                content += response[i]['alert'];
            }

            this._createResponse(changeContent, content);
        } else {
            window.location.reload();
        }
    }

    _createResponse(changeContent, content) {
        if(changeContent) {
            if(this._confirmationText) {
                content = this._confirmationText;
            }
            this._block.innerHTML = '<div class="confirm-message">' + content + '</div>';
        } else {
            const confirmDiv = this._block.querySelector('.confirm-alert');
            if(confirmDiv) {
                confirmDiv.remove();
            }
            const html = '<div class="confirm-alert">' + content + '</div>';
            this._block.insertAdjacentHTML('beforeend', html);
        }

        this._block.scrollIntoView({
            behavior: 'smooth',
            block: 'end'
        });
    }
}
