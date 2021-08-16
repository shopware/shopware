export interface NextFunction {
    error: null;
    next: () => void;
    mergedData: object;
}

export class MiddlewareHelper {
    constructor();

    get stack(): NextFunction[];

    use(middleware: NextFunction): MiddlewareHelper;

    go(...args: any[]): void;
}
