/**
 * @sw-package framework
 */
import type { AxiosInstance } from 'axios';
import ApiService from '../api.service';
import type { LoginService } from '../login.service';

type MatchingFingerprint = {
    identifier: string;
    storedStamp: string;
    score: number;
};

type MismatchingFingerprint = {
    identifier: string;
    storedStamp: string;
    expectedStamp: string;
    score: number;
};

type FingerprintComparisonResult = {
    matchingFingerprints: MatchingFingerprint[];
    mismatchingFingerprints: MismatchingFingerprint[];
    score: number;
    threshold: number;
};

type Strategy = {
    name: string;
    description: string;
};

type ShopIdCheck = {
    fingerprints: FingerprintComparisonResult;
    apps: string[];
};

/**
 * @private
 */
export default class ShopIdChangeService extends ApiService {
    constructor(httpClient: AxiosInstance, loginService: LoginService) {
        super(httpClient, loginService, '', 'application/json');
        this.name = 'shopIdChangeService';
    }

    getChangeStrategies() {
        return this.httpClient
            .get('app-system/shop-id/change-strategies', {
                headers: this.getBasicHeaders(),
            })
            .then(({ data }: { data: Record<string, string> }) => {
                return Object.entries(data).map(
                    ([
                        key,
                        description,
                    ]) => {
                        return { name: key, description };
                    },
                );
            });
    }

    changeShopId({ name }: Strategy) {
        return this.httpClient.post(
            'app-system/shop-id/change',
            { strategy: name },
            {
                headers: this.getBasicHeaders(),
            },
        );
    }

    checkShopId() {
        return this.httpClient
            .post(
                'app-system/shop-id/check',
                {},
                {
                    headers: this.getBasicHeaders(),
                },
            )
            .then((resp) => {
                if (resp.status === 204) {
                    return null;
                }
                return resp.data as ShopIdCheck;
            });
    }
}

/**
 * @private
 */
export type { ShopIdCheck, Strategy };
