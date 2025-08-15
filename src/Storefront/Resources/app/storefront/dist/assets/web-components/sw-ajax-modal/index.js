export default class SwAjaxModal extends HTMLElement {

    constructor() {
        super();

        this.attachShadow({ mode: 'open' });
        this.shadowRoot.innerHTML = this.template();
    }

    template() {
        return `
            <link href="${window.themeWebComponentsPath}/vendor/bootstrap.min.css" rel="stylesheet">
            <a role="button" class="sw-ajax-modal-link fs-6" href="${this.url}" data-url="${this.url}"><slot></slot></a>
        `;
    }

    static modalTemplate = (() => {
        const template = document.createElement('template');
        template.innerHTML = `
            <div id="modal" class="modal" tabindex="-1">
              <div class="modal-dialog">
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title">Modal title</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                  </div>
                  <div class="modal-body">
                    <p>Modal body text goes here.</p>
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary">Save changes</button>
                  </div>
                </div>
              </div>
            </div>
        `;
        return template;
    })();

    connectedCallback() {
        this.addEventListener('click', this.openModal);
    }

    openModal(event) {
        event.preventDefault();

        if (!this.url) {
            console.warn('[AjaxModal] No "data-url" found. Please provide a valid URL');
            return;
        }

        this.shadowRoot.appendChild(SwAjaxModal.modalTemplate.content.cloneNode(true));
        this.modalEl = this.shadowRoot.getElementById('modal');

        const bootstrapModal = new bootstrap.Modal(this.modalEl);
        bootstrapModal.show();
    }

    /**
     * @return {string}
     */
    get url() {
        return this.getAttribute('data-url');
    }
}
