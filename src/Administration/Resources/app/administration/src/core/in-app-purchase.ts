/**
 * @package admin
 * @module core/in-app-purchase
 * A static registry containing a list of all in-app purchases
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class InAppPurchase {
    static inAppPurchase: Record<string, string> = {};

    static init(inAppPurchase: Record<string, string>): void {
        this.inAppPurchase = inAppPurchase;
    }

    static getAll(): Record<string, string> {
        return this.inAppPurchase;
    }

    static getByExtension(extensionId: string): string[] {
        return Object.entries(this.inAppPurchase)
            .filter(
                ([
                    ,
                    value,
                ]) => value === extensionId,
            )
            .map(([key]) => key);
    }

    static isActive(inAppPurchase: string): boolean {
        return Object.values(this.inAppPurchase).some((value) => value === inAppPurchase);
    }
}
