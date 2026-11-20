// Cookie-consent gate for embedded videos. Replaces the SSR consent placeholder with the
// actual iframe once the visitor accepts, and reacts to consent granted elsewhere.
// Native document events are used (matching the component system): the shared cookie
// plugin dispatches `CookieConfiguration_Update` on `document`, and an inline accept on
// any embed broadcasts `SwMediaEmbedVideo_ConsentGranted` so sibling embeds reveal too.
const COOKIE_CONFIGURATION_UPDATE = 'CookieConfiguration_Update';
const CONSENT_GRANTED = 'SwMediaEmbedVideo_ConsentGranted';

export default class MediaEmbedVideo extends ShopwareComponent {
    static options = {
        videoUrl: null,
        videoTitle: null,
        cookieName: null,
        allow: '',
    };

    init() {
        this.acceptButton = this.el.querySelector('.sw-media-embed-video__accept');
        this.onAccept = this.onAccept.bind(this);
        this.onConsentUpdate = this.onConsentUpdate.bind(this);

        // Consent already given (e.g. accepted on a previous visit) — show the video right away.
        if (this.hasConsent()) {
            this.replaceWithVideo();
            return;
        }

        if (this.acceptButton) {
            this.acceptButton.addEventListener('click', this.onAccept);
        }

        // React to the cookie-settings modal and to an inline accept on any other embed of
        // the same platform, so every gated embed reveals itself from a single consent.
        document.addEventListener(COOKIE_CONFIGURATION_UPDATE, this.onConsentUpdate);
        document.addEventListener(CONSENT_GRANTED, this.onConsentUpdate);
    }

    destroy() {
        if (this.acceptButton) {
            this.acceptButton.removeEventListener('click', this.onAccept);
        }

        document.removeEventListener(COOKIE_CONFIGURATION_UPDATE, this.onConsentUpdate);
        document.removeEventListener(CONSENT_GRANTED, this.onConsentUpdate);
    }

    hasConsent() {
        const prefix = `${this.options.cookieName}=`;

        return document.cookie.split(';').some((cookie) => cookie.trim().indexOf(prefix) === 0);
    }

    setConsentCookie() {
        const expires = new Date();
        expires.setTime(expires.getTime() + 30 * 24 * 60 * 60 * 1000);
        const secure = window.location.protocol === 'https:' ? ';secure' : '';

        document.cookie = `${this.options.cookieName}=1;expires=${expires.toUTCString()};path=/;sameSite=lax${secure}`;
    }

    onAccept(event) {
        event.preventDefault();
        this.setConsentCookie();
        this.replaceWithVideo();

        // Broadcast so sibling embeds sharing this cookie (same platform) replace themselves
        // too — each with its own configured video. Mirrors the legacy GDPR video plugin.
        document.dispatchEvent(new CustomEvent(CONSENT_GRANTED));
    }

    onConsentUpdate() {
        if (this.hasConsent()) {
            this.replaceWithVideo();
        }
    }

    replaceWithVideo() {
        // The consent element was already swapped out (or detached) — nothing to do.
        if (!this.el.parentNode || !this.options.videoUrl) {
            return;
        }

        const iframe = document.createElement('iframe');
        iframe.classList.add('sw-media-embed-video__video');
        iframe.setAttribute('src', this.options.videoUrl);
        iframe.setAttribute('allowfullscreen', 'allowfullscreen');

        if (this.options.videoTitle) {
            iframe.setAttribute('title', this.options.videoTitle);
        }

        if (this.options.allow) {
            iframe.setAttribute('allow', this.options.allow);
        }

        this.el.parentNode.replaceChild(iframe, this.el);
    }
}
