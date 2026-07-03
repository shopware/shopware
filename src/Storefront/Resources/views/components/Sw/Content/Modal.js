({ Shopware, ShopwareComponent } = window);

export default class Modal extends ShopwareComponent {

    static options = {
        ajaxUrl: null,
        setTitleFromAjaxContent: false,
    };

    init() {
        this.modalBody = this.el.querySelector('.sw-modal__body');
        this.modalTitle = this.el.querySelector('.sw-modal__title');

        this.registerRemoteModalEvents();
    }

    registerRemoteModalEvents() {
        if (!this.options.ajaxUrl) {
            return;
        }

        this.el.addEventListener('show.bs.modal', this.addLoadingState.bind(this));
        this.el.addEventListener('shown.bs.modal', this.fetchContent.bind(this));
    }

    addLoadingState() {
        if (this.options.setTitleFromAjaxContent) {
            this.modalTitle.classList.add('placeholder-glow', 'w-25');
            this.modalTitle.innerHTML = '<span class="placeholder col-12"></span>';
        }

        this.modalBody.innerHTML = this.getLoaderTemplate();
    }

    fetchContent() {
        fetch(this.options.ajaxUrl)
            .then(response => response.text())
            .then(content => this.renderContent(content))
            .catch(error => this.handleError(error))
    }

    renderContent(content) {
        ({ content } = Shopware.emitInterception(`Modal:PreRenderContent`, { content }));

         this.modalBody.innerHTML = content;

        if (this.options.setTitleFromAjaxContent) {
            this.moveFirstHeadlineToModalTitle();
        }
    }

    /**
     * @experimental
     *
     * The ajax content can be a CMS page that can already contain a headline/title itself.
     * This duplicates the actual Modal title which is not part of the `modal-body` where the ajax content is rendered.
     * When `setTitleFromAjaxContent` is true, we get the first headline and move it to the modal-title
     * to have a proper modal structure.
     * This overrides the `title` set on the twig component.
     */
    moveFirstHeadlineToModalTitle() {
        const firstHeadline = this.modalBody.querySelector('h1, h2, h3, h4, h5, h6');
        const nextElement = firstHeadline.nextElementSibling;

        firstHeadline.remove();

        if (nextElement && nextElement.tagName === 'HR') {
            nextElement.remove();
        }

        this.modalTitle.textContent = firstHeadline.textContent;
    }

    handleError(error) {
        console.error(error);
    }

    getLoaderTemplate() {
        return `
            <p class="card-text placeholder-glow">
                <span class="placeholder col-7"></span>
                <span class="placeholder col-4"></span>
                <span class="placeholder col-4"></span>
                <span class="placeholder col-6"></span>
                <span class="placeholder col-8"></span>
                <span class="placeholder col-12"></span>
                <span class="placeholder col-7"></span>
                <span class="placeholder col-2"></span>
                <span class="placeholder col-8"></span>
            </p>
        `;
    }

    destroy() {
        this.el.removeEventListener('show.bs.modal', this.addLoadingState.bind(this));
        this.el.removeEventListener('shown.bs.modal', this.fetchContent.bind(this));
    }
}
