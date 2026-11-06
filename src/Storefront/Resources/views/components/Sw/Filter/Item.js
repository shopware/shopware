export default class FilterItem extends ShopwareComponent {

    init() {
        this.filterElement = this.el.querySelector('.sw-filter');
        this.countBadge = this.el.querySelector('.sw-filter-item__count');

        this.registerEvents();
    }

    registerEvents() {
        this.filterElement.addEventListener('Filter:UpdateBadge', this.handleFilterUpdateBadge.bind(this));
    }

    reset() {
        this.updateBadge('');
    }

    updateBadge(content) {
        this.countBadge.textContent = content ? `${content}` : '';

        if (content?.length > 0) {
            this.countBadge.classList.remove('is--hidden');
        } else {
            this.countBadge.classList.add('is--hidden');
        }
    }

    handleFilterUpdateBadge(event) {
        const { content } = event.detail;
        this.updateBadge(content);
    }

    destroy() {
        this.filterElement.removeEventListener('Filter:UpdateBadge', this.handleFilterUpdateBadge.bind(this));
    }
}
