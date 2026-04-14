import { ShopwareComponent, Shopware } from 'shopware';

interface PaginationOptions extends Record<string, unknown> {
    useHref: boolean;
}

export default class Pagination extends ShopwareComponent {

    static options: PaginationOptions = {
        useHref: false,
    };

    declare el: HTMLElement;
    declare options: PaginationOptions;

    private items!: NodeListOf<HTMLAnchorElement>;
    private boundHandlers!: Map<HTMLAnchorElement, (event: MouseEvent) => void>;

    init(): void {
        this.items = this.el.querySelectorAll<HTMLAnchorElement>('a[data-page]');
        this.boundHandlers = new Map();

        this.items.forEach(item => {
            const handler = this.handleClick.bind(this, item);
            this.boundHandlers.set(item, handler);
            item.addEventListener('click', handler);
        });
    }

    private handleClick(item: HTMLAnchorElement, event: MouseEvent): void {
        if (!this.options.useHref) {
            event.preventDefault();
        }

        let page = parseInt(item.getAttribute('data-page') ?? '1', 10);

        ({ page } = Shopware.emitInterception('Pagination:PreChange', { page }) as { page: number });

        Shopware.emit('Pagination:Change', page);

        this.setActiveItem(page);
    }

    private setActiveItem(page: number): void {
        this.items.forEach(item => {
            item.parentElement?.classList.remove('active');
        });

        this.el.querySelector(`.page-${page}`)?.classList.add('active');
    }

    destroy(): void {
        this.items.forEach(item => {
            const handler = this.boundHandlers.get(item);
            if (handler) {
                item.removeEventListener('click', handler);
            }
        });
    }
}
