/**
 * @package checkout
 * @module core/in-app-purchase
 * A registry containing a list of all in-app purchases
 */
class InAppPurchase {
    public flattened(): string[] {
        return Object.entries(this.all()).flatMap(
            ([
                key,
                values,
            ]) => values.map((value: string) => `${key}-${value}`),
        );
    }

    public all(): Record<string, string[]> {
        return Shopware.State.get('context').app.config.inAppPurchases;
    }

    public getByExtension(extensionName: string): string[] {
        const extensions = this.all();
        return extensions[extensionName] || [];
    }

    public isActive(extensionName: string, inAppPurchase: string): boolean {
        const extensions = this.all();
        return extensions[extensionName]?.includes(inAppPurchase) || false;
    }
}

/**
 * @private
 */
export default Object.freeze(new InAppPurchase());
