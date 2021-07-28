export class RefreshTokenHelper {
    constructor();

    subscribe(callback?: () => void, errorCallback?: () => void): void;

    onRefreshToken(token: string): void;

    onRefreshTokenFailed(err: Error): void;

    fireRefreshTokenRequest(): Promise<string>;

    set isRefreshing(arg: boolean);

    get isRefreshing(): boolean;

    set whitelist(urls: string[]);

    get whitelist(): string[];
}
