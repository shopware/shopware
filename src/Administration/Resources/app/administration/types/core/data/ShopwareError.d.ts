export class ShopwareError {
    constructor(code: string, meta?: object, status?: string, detail?: string);

    get id(): any;

    set code(value: string);

    get code(): string;

    set parameters(value: any);

    get parameters(): any;

    set status(arg: string);

    get status(): string;

    set detail(value: string);

    get detail(): string;
}
