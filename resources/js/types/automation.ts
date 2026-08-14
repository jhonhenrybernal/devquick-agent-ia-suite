export type TelegramConfiguration = {
    botUsername?: string | null;
    chatId?: string | null;
    botToken?: string | null;
    webhookSecret?: string | null;
    webhookUrl?: string | null;
    registeredWebhookUrl?: string | null;
    webhookMatchesExpectedUrl?: boolean;
    webhookPendingUpdateCount?: number | null;
    webhookLastErrorMessage?: string | null;
    webhookLastErrorDate?: string | null;
    webhookStatusDescription?: string | null;
    webhookStatusOk?: boolean;
    isEnabled: boolean;
    hasToken: boolean;
    hasWebhookSecret?: boolean;
};

export type TelegramInboundMessage = {
    id: number;
    direction?: 'inbound' | 'outbound' | null;
    updateId?: number | null;
    updateType?: string | null;
    chatId?: string | null;
    fromUserId?: string | null;
    fromUsername?: string | null;
    messageText?: string | null;
    payload: Record<string, unknown>;
    syncStatus?: string | null;
    syncDescription?: string | null;
    syncResponseText?: string | null;
    syncMode?: string | null;
    syncReason?: string | null;
    syncProvider?: string | null;
    syncModel?: string | null;
    syncTool?: string | null;
    trainingStatus?: 'pending' | 'approved' | 'rejected' | null;
    trainingKind?: string | null;
    trainingLabel?: string | null;
    trainingContent?: string | null;
    trainingCapturedAt?: string | null;
    trainingUpdatedAt?: string | null;
    createdAt?: string | null;
};

export type DolibarrConfiguration = {
    apiLogin?: string | null;
    apiPassword?: string | null;
    apiUrl?: string | null;
    hasApiLogin: boolean;
    hasApiPassword: boolean;
    hasApiUrl: boolean;
    discoveredApis: string[];
    importantApis: string[];
    discoveredApiCount: number;
    importantApiCount: number;
    lastDiscoveredAt?: string | null;
    setupUrl: string;
};

export type AiProviderConfiguration = {
    provider: 'openai' | 'gemini' | 'ollama';
    providerLabel: string;
    model?: string | null;
    apiKey?: string | null;
    baseUrl?: string | null;
    isEnabled: boolean;
    hasApiKey: boolean;
    setupUrl: string;
    isLocal: boolean;
};

export type AiProviderOption = {
    value: 'openai' | 'gemini' | 'ollama';
    label: string;
    description: string;
};

export type AutomationSummary = {
    total: number;
    enabled: number;
};

export type TelegramTrainingSummary = {
    total: number;
    pending: number;
    approved: number;
    rejected: number;
};

export type AutomationDolibarrSummary = {
    hasApiLogin: boolean;
    hasApiPassword: boolean;
    hasApiUrl: boolean;
    discoveredApiCount: number;
    importantApiCount: number;
    setupUrl: string;
};

export type AutomationAiProviderSummary = {
    provider: 'openai' | 'gemini' | 'ollama';
    providerLabel: string;
    model: string;
    isEnabled: boolean;
    hasApiKey: boolean;
    setupUrl: string;
    isLocal: boolean;
};

export type AutomationAgent = {
    id: number;
    parentAgentId?: number | null;
    parentAgentName?: string | null;
    childAgentsCount?: number;
    name: string;
    description?: string | null;
    instructions: string;
    triggerKeyword?: string | null;
    targetTool: string;
    isEnabled: boolean;
    createdAt?: string | null;
};

export type ScheduledAutomationRun = {
    id: number;
    status: 'queued' | 'running' | 'success' | 'failed' | 'skipped';
    startedAt?: string | null;
    finishedAt?: string | null;
    inputPayload?: Record<string, unknown> | null;
    outputPayload?: Record<string, unknown> | null;
    errorMessage?: string | null;
};

export type ScheduledAutomationApproval = {
    id: number;
    status: 'pending' | 'approved' | 'rejected';
    approvedAt?: string | null;
    notes?: string | null;
};

export type ScheduledAutomation = {
    id: number;
    name: string;
    description?: string | null;
    status: 'draft' | 'active' | 'paused' | 'completed' | 'failed';
    triggerType: 'manual' | 'interval' | 'cron';
    cronExpression?: string | null;
    intervalValue?: number | null;
    intervalUnit?: 'minutes' | 'hours' | 'days' | 'weeks' | 'months' | null;
    timezone: string;
    nextRunAt?: string | null;
    lastRunAt?: string | null;
    lastResult?: string | null;
    parentAgentId?: number | null;
    parentAgentName?: string | null;
    childAgentId?: number | null;
    childAgentName?: string | null;
    sourceMessageId?: number | null;
    payload: Record<string, unknown>;
    runsCount?: number;
    latestRun?: string | null;
    latestApproval?: string | null;
};
