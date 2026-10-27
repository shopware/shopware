export default class ProductReviewForm extends ShopwareComponent {

    init() {
        // The guest view renders only the login card, so there is nothing to wire up.
        this.formEl = this.el.querySelector('.sw-product-review-form__form');
        if (!this.formEl) {
            return;
        }

        this.successAlert = this.el.querySelector('.sw-product-review-form__success');
        this.errorAlert = this.el.querySelector('.sw-product-review-form__error');
        this.idInput = this.formEl.querySelector('input[name="id"]');

        // Sw:Form owns the AJAX submit and emits the outcome on the bus; we only react to our own form.
        this.onFormResponse = this.onFormResponse.bind(this);
        this.onFormError = this.onFormError.bind(this);
        window.Shopware.on('Form:Response', this.onFormResponse);
        window.Shopware.on('Form:Error', this.onFormError);

        this.initToggle();
    }

    destroy() {
        if (!this.formEl) {
            return;
        }

        window.Shopware.off('Form:Response', this.onFormResponse);
        window.Shopware.off('Form:Error', this.onFormError);

        this.destroyToggle();
    }

    isOwnForm(payload) {
        return Boolean(payload) && payload.form === this.formEl;
    }

    /**
     * A saved review is held for moderation, but the list shows a customer their own pending review, so
     * the Product Reviews element reloads to reveal it. Here the form gives way to the confirmation and the
     * edit teaser, so the review can be edited again.
     */
    onFormResponse(payload) {
        if (!this.isOwnForm(payload)) {
            return;
        }

        // Adopt the created/updated review id (from the JSON response) so a follow-up edit targets it
        // instead of creating a duplicate.
        const reviewId = payload.payload?.id;
        if (reviewId && this.idInput) {
            this.idInput.value = reviewId;
        }

        this.toggle(this.errorAlert, false);
        this.toggle(this.successAlert, true);

        this.formEl.classList.add('d-none');
        this.teaser?.classList.remove('d-none');
        // The review now exists, so editing it can be cancelled back to the teaser.
        this.cancelBtn?.classList.remove('d-none');
        this.successAlert?.focus();

        window.Shopware.emit('ReviewForm:Submitted', { form: this.formEl });
    }

    onFormError(payload) {
        if (!this.isOwnForm(payload)) {
            return;
        }

        this.toggle(this.successAlert, false);
        this.toggle(this.errorAlert, true);
    }

    toggle(el, visible) {
        if (!el) {
            return;
        }

        el.hidden = !visible;
        el.classList.toggle('d-none', !visible);
    }

    // --- Teaser toggle (editing an existing review) -----------------------------------------

    initToggle() {
        // Only rendered when the customer already has a review: a teaser stands in for the form.
        this.teaser = this.el.querySelector('.js-review-form-teaser');
        if (!this.teaser) {
            return;
        }

        this.onOpen = () => this.showForm();
        this.onCancel = () => this.showTeaser();

        this.toggleBtn = this.el.querySelector('.js-review-form-toggle');
        this.cancelBtn = this.el.querySelector('.js-review-form-cancel');

        this.toggleBtn?.addEventListener('click', this.onOpen);
        this.cancelBtn?.addEventListener('click', this.onCancel);
    }

    destroyToggle() {
        this.toggleBtn?.removeEventListener('click', this.onOpen);
        this.cancelBtn?.removeEventListener('click', this.onCancel);
    }

    showForm() {
        // Reopening to edit clears the previous round's confirmation/error.
        this.toggle(this.successAlert, false);
        this.toggle(this.errorAlert, false);

        this.teaser?.classList.add('d-none');
        this.formEl.classList.remove('d-none');
        this.formEl.querySelector('.sw-form-field__control')?.focus();
    }

    showTeaser() {
        this.formEl.classList.add('d-none');
        this.teaser?.classList.remove('d-none');
        this.toggleBtn?.focus();
    }
}
