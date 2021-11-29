import { handle } from '@shopware-ag/admin-extension-sdk/es/channel';

export default function initializeWindow(): void {
    // Handle incoming window requests from the ExtensionAPI
    handle('windowReload', () => {
        window.location.reload();
    });

    handle('windowRedirect', ({ newTab, url }) => {
        if (newTab) {
            window.open(url, '_blank');
        } else {
            window.location.href = url;
        }
    });
}
