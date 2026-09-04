/**
 * @sw-package framework
 */

import type { AxiosInstance } from 'axios';
import type { LoginService } from '../login.service';
import ApiService from '../api.service';

type ContentSystemElementTypePropertyPrimitive = string | number | boolean | null;

/**
 * @private
 */
export interface ContentSystemElementAdminUiVisibleWhenCondition {
    field: string;
    equals?: ContentSystemElementTypePropertyPrimitive;
    notEquals?: ContentSystemElementTypePropertyPrimitive;
    in?: ContentSystemElementTypePropertyPrimitive[];
    notIn?: ContentSystemElementTypePropertyPrimitive[];
    isEmpty?: boolean;
    isNotEmpty?: boolean;
}

/**
 * @private
 */
export type ContentSystemElementAdminUiVisibleWhen =
    | ContentSystemElementAdminUiVisibleWhenCondition
    | ContentSystemElementAdminUiVisibleWhenCondition[];

/**
 * @private
 */
export interface ContentSystemElementTypePropertyAdminUi {
    component?: string;
    props?: Record<string, unknown>;
    entity?: string;
    helpText?: string;
    panel?: string;
    visibleWhen?: ContentSystemElementAdminUiVisibleWhen;
    [key: string]: unknown;
}

/**
 * @private
 */
export interface ContentSystemElementTypeProperty {
    type: string | string[];
    translatable: boolean;
    enum: Array<string | number | boolean> | null;
    default: string | number | boolean | null;
    required: boolean;
    title: string;
    description: string;
    adminUI: ContentSystemElementTypePropertyAdminUi | null;
    properties?: Record<string, ContentSystemElementTypeProperty> | null;
}

/**
 * @private
 */
export interface ContentSystemElementTypeSlot {
    name: string;
    maxElements: number | null;
    allowList: string[];
    description: string;
}

/**
 * @private
 */
export interface ContentSystemElementTypeBindingResolve {
    loader: string;
    config: Record<string, unknown>;
}

/**
 * @private
 */
export interface ContentSystemElementTypeBindingSpecification {
    default: boolean;
    resolves: Record<string, ContentSystemElementTypeBindingResolve>;
}

/**
 * @private
 */
export interface ContentSystemElementTypeSpecification {
    name: string;
    label: string;
    description: string;
    source: string;
    icon: string | null;
    category: string | null;
    copilot: {
        summary: string;
        hints: string[];
    };
    properties: Record<string, ContentSystemElementTypeProperty>;
    slots: ContentSystemElementTypeSlot[];
    bindingSpecifications?: Record<string, ContentSystemElementTypeBindingSpecification>;
}

/**
 * @private
 */
export interface ContentSystemElementTypeResponse {
    types: ContentSystemElementTypeSpecification[];
}

/**
 * Gateway for content system element types endpoint.
 */
class ContentSystemElementTypeApiService extends ApiService {
    constructor(httpClient: AxiosInstance, loginService: LoginService, apiEndpoint = 'content-system-element-types') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'contentSystemElementTypeService';
    }

    getTypes(): Promise<ContentSystemElementTypeSpecification[]> {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .get<ContentSystemElementTypeResponse>('/_info/content-system-element-types.json', {
                headers,
            })
            .then((response) => {
                const payload = ApiService.handleResponse<ContentSystemElementTypeResponse>(response);

                return Array.isArray(payload.types) ? payload.types : [];
            });
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default ContentSystemElementTypeApiService;
