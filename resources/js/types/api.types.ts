/**
 * SkillSwap Campus — Types Génériques API REST
 */

export interface LaravelPaginatedResponse<T> {
    current_page: number;
    data: T[];
    first_page_url?: string;
    from?: number | null;
    last_page: number;
    last_page_url?: string;
    links?: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
    next_page_url?: string | null;
    path?: string;
    per_page: number;
    prev_page_url?: string | null;
    to?: number | null;
    total: number;
}

export interface ApiSuccessResponse<T = unknown> {
    message?: string;
    data?: T;
    [key: string]: unknown;
}

export interface ApiErrorResponse {
    message: string;
    error?: string;
    errors?: Record<string, string[]>;
    statusCode?: number;
}
