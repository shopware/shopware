import Plugin from 'src/plugin-system/plugin.class';

export default class InfiniteScrollPlugin extends Plugin {

    static options = {
        dataUrl: '',
        limit: 24,
        total: 0,
        currentPage: 1,
        pagesUntilButton: 2,
        scrollThreshold: 400,
        loadMoreButtonSelector: '.val-infinite-scroll-load-more',
        loadedCountSelector: '.val-infinite-scroll-count',
        productContainerSelector: '.js-listing-wrapper',
        loadingClass: 'val-infinite-scroll--loading',
    };

    init() {
        this._currentPage = parseInt(this.options.currentPage, 10);
        this._totalPages = Math.floor(this.options.total / this.options.limit);
        this._isLoading = false;
        this._autoLoadCount = 0;

        this._loadMoreButton = this.el.querySelector(this.options.loadMoreButtonSelector);
        this._loadedCountEl = this.el.querySelector(this.options.loadedCountSelector);
        this._productContainer = document.querySelector(this.options.productContainerSelector);

        this._registerScrollEvent();
        this._registerLoadMoreEvent();
        this._updateLoadedCount();
    }

    _registerScrollEvent() {
        window.addEventListener('scroll', this._onScroll.bind(this));
    }

    _onScroll() {
        if (this._isLoading) {
            return;
        }

        if (this._currentPage >= this._totalPages) {
            return;
        }

        if (this._autoLoadCount >= this.options.pagesUntilButton) {
            return;
        }

        const scrollBottom = window.scrollY + window.innerHeight;
        const triggerAt = document.body.offsetHeight - this.options.scrollThreshold;

        if (scrollBottom >= triggerAt) {
            this._loadNextPage();
        }
    }

    _loadNextPage() {
        if (this._isLoading || this._currentPage >= this._totalPages) {
            return;
        }

        this._isLoading = true;
        this._currentPage += 1;
        this._autoLoadCount += 1;

        this.el.classList.add(this.options.loadingClass);

        if (this._autoLoadCount >= this.options.pagesUntilButton) {
            this._showLoadMoreButton();
        }

        this._fetchPage(this._currentPage);
    }

    _fetchPage(page) {
        const params = new URLSearchParams(window.location.search);
        params.set('p', String(page));

        const url = `${this.options.dataUrl}?${params.toString()}`;

        fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(response => response.text())
            .then(html => {
                this._appendProducts(html);
                this._isLoading = false;
                this.el.classList.remove(this.options.loadingClass);
                this._updateLoadedCount();

                if (this._currentPage >= this._totalPages) {
                    this._hideLoadMoreButton();
                }
            })
            .catch(() => {
                this._currentPage -= 1;
                this._autoLoadCount -= 1;
                this._isLoading = false;
                this.el.classList.remove(this.options.loadingClass);
            });
    }

    _appendProducts(html) {
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const newProducts = doc.querySelectorAll('.cms-listing-col');

        if (!this._productContainer || !newProducts.length) {
            return;
        }

        newProducts.forEach(product => {
            this._productContainer.appendChild(product.cloneNode(true));
        });

        window.PluginManager.initializePlugins();
    }

    _updateLoadedCount() {
        if (!this._loadedCountEl) {
            return;
        }

        const loaded = this._currentPage * this.options.limit;
        const template = this._loadedCountEl.dataset.countTemplate;

        if (template) {
            this._loadedCountEl.textContent = template
                .replace('__loaded__', loaded)
                .replace('__total__', this.options.total);
        }
    }

    _showLoadMoreButton() {
        if (this._loadMoreButton) {
            this._loadMoreButton.classList.remove('d-none');
        }
    }

    _hideLoadMoreButton() {
        if (this._loadMoreButton) {
            this._loadMoreButton.classList.add('d-none');
        }
    }

    _registerLoadMoreEvent() {
        if (!this._loadMoreButton) {
            return;
        }

        this._loadMoreButton.addEventListener('click', this._onLoadMoreClick.bind(this));
    }

    _onLoadMoreClick() {
        if (this._isLoading || this._currentPage >= this._totalPages) {
            return;
        }

        this._currentPage += 1;
        this._isLoading = true;
        this.el.classList.add(this.options.loadingClass);

        if (this._currentPage >= this._totalPages) {
            this._hideLoadMoreButton();
        }

        this._fetchPage(this._currentPage);
    }

    destroy() {
        window.removeEventListener('scroll', this._onScroll.bind(this));
        super.destroy();
    }
}
