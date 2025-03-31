import Plugin from 'src/plugin-system/plugin.class';
/** @deprecated tag:v6.8.0 - HttpClient is deprecated. Use native fetch API instead. */
import HttpClient from 'src/service/http-client.service';

/**
 * @package discovery
 */
export default class FormCmsHandler extends Plugin {

    static options = {
        hiddenClass: 'd-none',
        hiddenSubmitSelector: '.submit--hidden',
        formContentSelector: '.form-content',
        cmsBlock: '.cms-block',
        /**
         * @deprecated tag:v6.8.0 - Option contentType will be removed.
         * The option was never effecting the actual request because the HttpClient automatically resets the Content-Type for FormData requests.
         */
        contentType: 'application/x-www-form-urlencoded',
    };

    init() {
        /** @deprecated tag:v6.8.0 - HttpClient is deprecated. Use native fetch API instead. */
        this._client = new HttpClient();
        this._getHiddenSubmit();
        this._registerEvents();
        this._getCmsBlock();
        this._getConfirmationText();
    }

    sendAjaxFormSubmit() {
        const _data = new FormData(this.el);

        fetch(this.el.action, {
            method: 'POST',
            body: _data,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then(response => response.text())
            .then(content => this._handleResponse(content));
    }

    _registerEvents() {
        this.el.addEventListener('submit', this._handleSubmit.bind(this));
    }

    _getConfirmationText() {
        const input = this.el.querySelector('input[name="confirmationText"]');
        if (input) {
            this._confirmationText = input.value;
        }
    }

    _getCmsBlock() {
        this._block = this.el.closest(this.options.cmsBlock);
    }

    _getHiddenSubmit() {
        this._hiddenSubmit = this.el.querySelector(this.options.hiddenSubmitSelector);
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
        this.$emitter.publish('beforeSubmit');

        this.sendAjaxFormSubmit();
    }

    _handleResponse(res) {
        const response = JSON.parse(res);
        this.$emitter.publish('onFormResponse', res);

        if (response.length > 0) {
            let changeContent = true;
            let content = '';
            for (let i = 0; i < response.length; i += 1) {
                if (response[i].type === 'danger' || response[i].type === 'info') {
                    changeContent = false;
                }
                content += response[i].alert;
            }

            // Reset form after successful submission to clear form contents.
            if (changeContent) {
                this.el.reset();
            }

            this._createResponse(changeContent, content);
        } else {
            window.location.reload();
        }
    }

    _createResponse(changeContent, content) {
        if (changeContent) {
            if (this._confirmationText) {
                content = this._confirmationText;
            }
            this._block.innerHTML = `<div class="confirm-message">${content}</div>`;
        } else {
            const confirmDiv = this._block.querySelector('.confirm-alert');
            if (confirmDiv) {
                confirmDiv.remove();
            }
            const html = `<div class="confirm-alert">${content}</div>`;
            this._block.insertAdjacentHTML('beforeend', html);
        }

        this._block.scrollIntoView({
            behavior: 'smooth',
            block: 'end',
        });
    }
}
