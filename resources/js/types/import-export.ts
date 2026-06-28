export type ImportFormat = 'postman_v2' | 'openapi_3' | 'swagger_2' | 'curl' | 'har' | 'insomnia';
export type ExportFormat = 'postman_v2' | 'openapi_3' | 'curl' | 'har';
export type ImportStatus = 'pending' | 'previewing' | 'processing' | 'completed' | 'failed';
export type ExportStatus = 'processing' | 'completed' | 'failed';
export type MergeStrategy = 'create_new' | 'merge_replace' | 'merge_skip';

export interface ValidationMessage {
    level: 'error' | 'warning' | 'info';
    message: string;
    path?: string;
}

export interface ParsedRequest {
    name: string;
    method: string;
    url: string;
    headers: Array<{ key: string; value: string }>;
    query_params: Array<{ key: string; value: string }>;
    body: Record<string, unknown>;
    auth: Record<string, unknown>;
}

export interface ParsedFolder {
    name: string;
    description?: string;
    requests: ParsedRequest[];
}

export interface ImportPreview {
    collection_name: string;
    collection_description?: string;
    folders: ParsedFolder[];
    requests: ParsedRequest[];
    validation_messages: ValidationMessage[];
}

export interface ImportRecord {
    id: string;
    team_id: string;
    user_id: string;
    source_format: ImportFormat;
    original_filename: string;
    status: ImportStatus;
    target_collection_id?: string;
    merge_strategy: MergeStrategy;
    summary: {
        collections: number;
        folders: number;
        requests: number;
        warnings: number;
        errors: number;
    };
    validation_report: ValidationMessage[];
    parsed_data: ImportPreview;
    error_message?: string;
    created_at: string;
    updated_at: string;
}

export interface ExportRecord {
    id: string;
    team_id: string;
    collection_id: string;
    target_format: ExportFormat;
    filename: string;
    status: ExportStatus;
    error_message?: string;
    created_at: string;
    updated_at: string;
}

export interface ConflictItem {
    request_name: string;
    method: string;
    url: string;
    existing_request_id?: string;
    incoming: ParsedRequest;
    existing: ParsedRequest;
}

export const FORMAT_LABELS: Record<ImportFormat | ExportFormat, string> = {
    postman_v2: 'Postman v2',
    openapi_3: 'OpenAPI 3.x',
    swagger_2: 'Swagger 2.0',
    curl: 'cURL',
    har: 'HAR',
    insomnia: 'Insomnia',
};

export const FORMAT_COLORS: Record<ImportFormat | ExportFormat, string> = {
    postman_v2: '#ff6c37',
    openapi_3: '#6ba539',
    swagger_2: '#85ea2d',
    curl: '#073551',
    har: '#4a90d9',
    insomnia: '#7b2ff2',
};
