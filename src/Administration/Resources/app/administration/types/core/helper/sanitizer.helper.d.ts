export interface SanitizerHelper {
    setConfig(config: object): void;

    clearConfig(): void;

    addMiddleware(middlewareName: string, middlewareFn?: () => void): boolean;

    removeMiddleware(middlewareName: string): boolean;

    sanitize(dirtyHtml: string, config?: object): string;
}
