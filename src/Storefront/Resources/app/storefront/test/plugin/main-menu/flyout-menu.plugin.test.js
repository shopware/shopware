import FlyoutMenuPlugin from 'src/plugin/main-menu/flyout-menu.plugin';
import Feature from 'src/helper/feature.helper';

const html = `<div class="main-navigation" id="mainNavigation" data-flyout-menu="true">
            <div class="container">
            <nav class="nav main-navigation-menu" itemscope="itemscope" itemtype="https://schema.org/SiteNavigationElement">
                <a class="nav-link main-navigation-link home-link" href="/" itemprop="url" title="Home">
                    <div class="main-navigation-link-text">
                        <span itemprop="name">Home</span>
                    </div>
                </a>
                <a class="nav-link main-navigation-link" href="http://localhost:8000/Clothing-Garden-Baby/" itemprop="url"
                   data-flyout-menu-trigger="018f71e4ba8171a6a6277224ead4baef" title="Clothing, Garden &amp; Baby">
                    <div class="main-navigation-link-text">
                        <span itemprop="name">Clothing, Garden &amp; Baby</span>
                    </div>
                </a>
                <div class="navigation-flyouts hidden">
                    <div class="navigation-flyout" data-flyout-menu-id="018f71e4ba8171a6a6277224ead4baef" style="top: 98px;">
                        <div class="container">
                            <div class="row navigation-flyout-bar">
                                <div class="col">
                                    <div class="navigation-flyout-category-link">
                                        <a class="nav-link" href="http://localhost:8000/Clothing-Garden-Baby/" itemprop="url"
                                           title="Clothing, Garden &amp; Baby">
                                            Show all Clothing, Garden &amp; Baby
                                            <span class="icon icon-arrow-right icon-primary">
                                       <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="16"
                                            height="16" viewBox="0 0 16 16">
                                          <use transform="rotate(-90 9 8.5)"
                                               xlink:href="#icons-solid-arrow-right" fill="#758CA3"
                                               fill-rule="evenodd"></use>
                                       </svg>
                                    </span>
                                        </a>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <div class="navigation-flyout-close js-close-flyout-menu">
                                 <span class="icon icon-x">
                                    <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24"
                                         height="24" viewBox="0 0 24 24">
                                       <use xlink:href="#icons-default-x" fill="#758CA3"
                                            fill-rule="evenodd"></use>
                                    </svg>
                                 </span>
                                    </div>
                                </div>
                            </div>
                            <div class="row navigation-flyout-content">
                                <div class="col-8 col-xl-9">
                                    <div class="navigation-flyout-categories">
                                        <div class="row navigation-flyout-categories is-level-0">
                                            <div class="col-4 navigation-flyout-col">
                                                <a class="nav-item nav-link navigation-flyout-link is-level-0"
                                                   href="http://localhost:8000/Clothing-Garden-Baby/Music-Clothing/" itemprop="url"
                                                   title="Music &amp; Clothing">
                                                    <span itemprop="name">Music &amp; Clothing</span>
                                                </a>
                                                <div class="navigation-flyout-categories is-level-1">
                                                    <div class="navigation-flyout-col">
                                                        <a class="nav-item nav-link navigation-flyout-link is-level-1"
                                                           href="http://localhost:8000/Clothing-Garden-Baby/Music-Clothing/Electronics-Baby-Jewelry/"
                                                           itemprop="url" title="Electronics, Baby &amp; Jewelry">
                                                            <span itemprop="name">Electronics, Baby &amp; Jewelry</span>
                                                        </a>
                                                        <div class="navigation-flyout-categories is-level-2">
                                                        </div>
                                                    </div>
                                                    <div class="navigation-flyout-col">
                                                        <a class="nav-item nav-link navigation-flyout-link is-level-1"
                                                           href="http://localhost:8000/Clothing-Garden-Baby/Music-Clothing/Jewelry-Home-Clothing/"
                                                           itemprop="url" title="Jewelry, Home &amp; Clothing">
                                                            <span itemprop="name">Jewelry, Home &amp; Clothing</span>
                                                        </a>
                                                        <div class="navigation-flyout-categories is-level-2">
                                                        </div>
                                                    </div>
                                                    <div class="navigation-flyout-col">
                                                        <a class="nav-item nav-link navigation-flyout-link is-level-1"
                                                           href="http://localhost:8000/Clothing-Garden-Baby/Music-Clothing/Beauty-Grocery-Clothing/"
                                                           itemprop="url" title="Beauty, Grocery &amp; Clothing">
                                                            <span itemprop="name">Beauty, Grocery &amp; Clothing</span>
                                                        </a>
                                                        <div class="navigation-flyout-categories is-level-2">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-4 navigation-flyout-col">
                                                <a class="nav-item nav-link navigation-flyout-link is-level-0"
                                                   href="http://localhost:8000/Clothing-Garden-Baby/Computers-Automotive-Home/"
                                                   itemprop="url" title="Computers, Automotive &amp; Home">
                                                    <span itemprop="name">Computers, Automotive &amp; Home</span>
                                                </a>
                                                <div class="navigation-flyout-categories is-level-1">
                                                    <div class="navigation-flyout-col">
                                                        <a class="nav-item nav-link navigation-flyout-link is-level-1"
                                                           href="http://localhost:8000/Clothing-Garden-Baby/Computers-Automotive-Home/Movies-Outdoors/"
                                                           itemprop="url" title="Movies &amp; Outdoors">
                                                            <span itemprop="name">Movies &amp; Outdoors</span>
                                                        </a>
                                                        <div class="navigation-flyout-categories is-level-2">
                                                        </div>
                                                    </div>
                                                    <div class="navigation-flyout-col">
                                                        <a class="nav-item nav-link navigation-flyout-link is-level-1"
                                                           href="http://localhost:8000/Clothing-Garden-Baby/Computers-Automotive-Home/Computers-Games-Shoes/"
                                                           itemprop="url" title="Computers, Games &amp; Shoes">
                                                            <span itemprop="name">Computers, Games &amp; Shoes</span>
                                                        </a>
                                                        <div class="navigation-flyout-categories is-level-2">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-4 col-xl-3">
                                    <div class="navigation-flyout-teaser">
                                        <a class="navigation-flyout-teaser-image-container"
                                           href="http://localhost:8000/Clothing-Garden-Baby/" title="Clothing, Garden &amp; Baby">
                                            <img
                                                src="http://localhost:8000/media/2a/4d/d4/1715602758/136c7b242af5da3555ac068e8f4b22ef.jpg?1715602758"
                                                class="navigation-flyout-teaser-image"
                                                title="File #3: /tmp/nix-shell.9D03Rs/136c7b242af5da3555ac068e8f4b22ef.jpg"
                                                data-object-fit="cover" loading="lazy">
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>
            </div>
            </div>
            <main><div>Some main content</div></main>`;

describe('FlyoutMenu tests', () => {
    let plugin;
    beforeEach(() => {
        document.body.innerHTML = html;
        plugin = new FlyoutMenuPlugin(document.querySelector('body'));

        jest.useFakeTimers();
    });

    afterEach(() => {
        jest.useRealTimers();
    });

    it('FlyoutMenuPlugin exists', () => {
        expect(plugin).toBeInstanceOf(FlyoutMenuPlugin);
    });

    it('FlyoutMenuPlugin calls init function', () => {
        plugin.init();
        expect(plugin._triggerEls.length).toBe(1);
        expect(plugin._closeEls.length).toBe(1);
        expect(plugin._flyoutEls.length).toBe(1);
        expect(plugin._hasOpenedFlyouts).toBe(false);
    });

    it('FlyoutMenuPlugin should open on mouseenter', () => {
        plugin.init();
        plugin._triggerEls[0].dispatchEvent(new Event('mouseenter'));
        jest.runAllTimers();
        expect(plugin._hasOpenedFlyouts).toBe(true);
    });

    it('FlyoutMenuPlugin should open on Keyboard Enter and close when main content is focused', () => {
        plugin.init();
        plugin._triggerEls[0].dispatchEvent(new KeyboardEvent('keydown', {code: 'Enter'}));
        jest.runAllTimers();
        expect(plugin._hasOpenedFlyouts).toBe(true);

        document.querySelector('main').dispatchEvent(new Event('focusin'));
        jest.runAllTimers();
        expect(plugin._hasOpenedFlyouts).toBe(false);
    });

    it('FlyoutMenuPlugin should open on mouseenter and close on mouseleave', () => {
        plugin.init();
        expect(plugin._hasOpenedFlyouts).toBe(false);
        plugin._triggerEls[0].dispatchEvent(new Event('mouseenter'));
        jest.runAllTimers();
        expect(plugin._hasOpenedFlyouts).toBe(true);

        plugin._triggerEls[0].dispatchEvent(new Event('mouseleave'));
        jest.runAllTimers();
        expect(plugin._hasOpenedFlyouts).toBe(false);
    });

    it('FlyoutMenuPlugin with Accessibility Flag true should remove hidden class', () => {
        window.Feature = Feature;
        window.Feature.init({'ACCESSIBILITY_TWEAKS': true});
        plugin.init();
        expect(plugin._hasOpenedFlyouts).toBe(false);
        plugin._triggerEls[0].dispatchEvent(new Event('mouseenter'));
        jest.runAllTimers();
        expect(plugin._hasOpenedFlyouts).toBe(true);
        expect(plugin._flyoutEls[0].classList.contains('hidden')).toBe(false);
    });

    it('FlyoutMenuPlugin with Accessibility Flag true top style should be remove after closing flyout', () => {
        window.Feature = Feature;
        window.Feature.init({'ACCESSIBILITY_TWEAKS': true});
        plugin.init();
        expect(plugin._hasOpenedFlyouts).toBe(false);
        plugin._triggerEls[0].dispatchEvent(new Event('mouseenter'));
        jest.runAllTimers();
        expect(plugin._hasOpenedFlyouts).toBe(true);
        expect(plugin._flyoutEls[0].style.top).toBe('98px');
        plugin._closeEls[0].dispatchEvent(new Event('click'));
        jest.runAllTimers();
        expect(plugin._hasOpenedFlyouts).toBe(false);
        expect(plugin._flyoutEls[0].style.top).toBe('');
    });
});
