export default function initializeWindow(): void {
    // Handle incoming window requests from the ExtensionAPI
    Shopware.ExtensionAPI.handle('windowReload', () => {
        window.location.reload();
    });

    Shopware.ExtensionAPI.handle('windowRedirect', ({ newTab, url }) => {
        if (newTab) {
            window.open(url, '_blank');
        } else {
            window.location.href = url;
        }
    });
}
