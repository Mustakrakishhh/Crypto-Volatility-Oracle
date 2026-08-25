export type OracleSourceReference = {
    key: string;
    name: string;
    type: 'market' | 'news';
    external_identifier: string;
};

export type OracleAsset = {
    id: number;
    symbol: string;
    name: string;
    base_currency: string;
    quote_currency: string;
    sources: OracleSourceReference[];
    market: {
        last_price: string | null;
        change_24h: number | null;
        volatility_24h: number | null;
        updated_at: string | null;
    };
    analysis: {
        status: 'waiting_for_history' | 'ready';
        data_completeness: number;
        observed_returns: number;
        expected_returns: number;
        window_started_at: string | null;
        window_ended_at: string | null;
        risk_score: number | null;
        risk_level: 'low' | 'moderate' | 'high' | 'extreme' | null;
        risk_label: string | null;
        factors: Array<{
            key: string;
            label: string;
            value: number;
            unit: string;
            contribution: number;
            max_contribution: number;
            explanation: string;
        }>;
    };
    forecast: {
        status: 'waiting_for_data' | 'collecting_history' | 'ready';
        label: string;
        confidence: number | null;
        risk: 'low' | 'moderate' | 'high' | 'extreme' | null;
        risk_label: string | null;
        horizon_hours: number;
        predicted_volatility: number | null;
        lower_bound: number | null;
        upper_bound: number | null;
        predicted_at: string | null;
        target_at: string | null;
        model_version: string | null;
        backtest: {
            sample_size: number;
            mae: number | null;
            rmse: number | null;
        };
        arguments: string[];
        failure_risks: string[];
    };
    model_comparison: {
        status: 'waiting_for_news' | 'ready';
        verdict:
            | 'insufficient_data'
            | 'candidate_better'
            | 'baseline_better'
            | 'tie';
        mae_improvement_pct: number | null;
        candidate: {
            model_version: string | null;
            predicted_volatility: number | null;
            confidence: number | null;
            sample_size: number;
            mae: number | null;
            rmse: number | null;
            news_inputs: {
                source: string;
                feature_date: string;
                lag_days: number;
                article_count: number;
                coverage_share_pct: number;
                trailing_coverage_share_pct: number;
                attention_ratio: number;
                average_tone: number;
                multiplier: number;
            } | null;
        };
    };
};

export type OracleDataSource = {
    id: number;
    key: string;
    name: string;
    type: 'market' | 'news';
    base_url: string;
    is_active: boolean;
    status: 'waiting' | 'syncing' | 'online' | 'error';
    latest_sync: OracleSyncRun | null;
    latest_attempt: OracleSyncRun | null;
};

export type OracleSyncRun = {
    status: string;
    started_at: string;
    finished_at: string | null;
    records_received: number;
    records_stored: number;
    error_message: string | null;
};

export type OracleOverview = {
    assets_count: number;
    sources_count: number;
    active_sources_count: number;
    forecast_horizon_hours: number;
    latest_sync_at: string | null;
    data_state: 'waiting' | 'ready';
};
