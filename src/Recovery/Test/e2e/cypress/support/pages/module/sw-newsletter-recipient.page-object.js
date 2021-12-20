import elements from '../sw-general.page-object';

export default class NewsletterRecipientPageObject {
    constructor() {
        this.elements = {
            ...elements,
            ...{
                newsletteSave: '.sw-newsletter-recipient-detail__open-edit-mode-action'
            }
        };
    }
}
