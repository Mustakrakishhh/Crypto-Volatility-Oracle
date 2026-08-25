<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    Activity,
    ArrowDownRight,
    CheckCircle2,
    ChevronDown,
    Clock3,
    Database,
    FlaskConical,
    Gauge,
    Newspaper,
    Radio,
    ShieldCheck,
    TriangleAlert,
    Waves,
} from '@lucide/vue';
import type { OracleAsset, OracleDataSource, OracleOverview } from '@/types';

defineProps<{
    assets: OracleAsset[];
    sources: OracleDataSource[];
    overview: OracleOverview;
}>();

const assetAccents: Record<string, string> = {
    BTC: 'from-amber-300 to-orange-500',
    ETH: 'from-indigo-300 to-violet-500',
    SOL: 'from-cyan-300 to-emerald-400',
};

const sourceTypeLabels: Record<OracleDataSource['type'], string> = {
    market: 'Рыночные данные',
    news: 'Новостной фон',
};

const sourceStatusLabels: Record<OracleDataSource['status'], string> = {
    waiting: 'Ожидает запуска',
    syncing: 'Синхронизация',
    online: 'Данные получены',
    error: 'Ошибка импорта',
};

const modelVerdictLabels: Record<
    OracleAsset['model_comparison']['verdict'],
    string
> = {
    insufficient_data: 'Недостаточно данных',
    candidate_better: 'Candidate лучше',
    baseline_better: 'Baseline лучше',
    tie: 'Результат равный',
};

const formatPrice = (price: string | null): string => {
    if (price === null) {
        return '—';
    }

    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 4,
    }).format(Number(price));
};

const formatDateTime = (date: string | null): string => {
    if (date === null) {
        return 'Ещё не запускалась';
    }

    return new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
        timeZone: 'Europe/Moscow',
    }).format(new Date(date));
};

const formatPercent = (value: number | null, signed = false): string => {
    if (value === null) {
        return '—';
    }

    const prefix = signed && value > 0 ? '+' : '';

    return `${prefix}${value.toFixed(2)}%`;
};

const riskTone = (level: OracleAsset['analysis']['risk_level']): string => {
    switch (level) {
        case 'low':
            return 'border-emerald-300/20 bg-emerald-300/8 text-emerald-200';
        case 'moderate':
            return 'border-amber-300/20 bg-amber-300/8 text-amber-200';
        case 'high':
            return 'border-orange-300/20 bg-orange-300/8 text-orange-200';
        case 'extreme':
            return 'border-rose-400/20 bg-rose-400/8 text-rose-300';
        default:
            return 'border-white/10 bg-white/5 text-slate-400';
    }
};
</script>

<template>
    <Head title="Crypto Volatility Oracle" />

    <div class="min-h-screen overflow-hidden bg-[#070a0d] text-slate-100">
        <div
            class="pointer-events-none fixed inset-0 [background-image:linear-gradient(rgba(255,255,255,0.025)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.025)_1px,transparent_1px)] [background-size:48px_48px] opacity-70"
        />
        <div
            class="pointer-events-none fixed -top-32 left-1/2 h-96 w-96 -translate-x-1/2 rounded-full bg-emerald-400/10 blur-[120px]"
        />

        <header
            class="relative border-b border-white/8 bg-[#070a0d]/85 backdrop-blur-xl"
        >
            <div
                class="mx-auto flex max-w-7xl items-center justify-between gap-5 px-5 py-5 sm:px-8 lg:px-10"
            >
                <a href="#overview" class="flex items-center gap-3">
                    <span
                        class="grid h-10 w-10 place-items-center rounded-xl border border-emerald-300/20 bg-emerald-300/8 text-emerald-300"
                    >
                        <Waves class="h-5 w-5" />
                    </span>
                    <span>
                        <span
                            class="block text-sm font-semibold tracking-[0.16em] text-white uppercase"
                        >
                            Future Oracle
                        </span>
                        <span
                            class="block text-[11px] tracking-[0.12em] text-slate-500 uppercase"
                        >
                            Volatility Research Desk
                        </span>
                    </span>
                </a>

                <nav
                    class="hidden items-center gap-7 text-sm text-slate-400 md:flex"
                >
                    <a class="transition hover:text-white" href="#overview"
                        >Обзор</a
                    >
                    <a class="transition hover:text-white" href="#sources"
                        >Источники</a
                    >
                    <a class="transition hover:text-white" href="#methodology"
                        >Методика</a
                    >
                </nav>

                <div
                    class="flex items-center gap-2 rounded-full border border-amber-300/15 bg-amber-300/7 px-3 py-1.5 text-xs font-medium text-amber-200"
                >
                    <span
                        class="h-1.5 w-1.5 animate-pulse rounded-full bg-amber-300"
                    />
                    Исследовательский прототип
                </div>
            </div>
        </header>

        <main
            class="relative mx-auto flex max-w-7xl flex-col gap-16 px-5 py-10 sm:px-8 sm:py-14 lg:px-10 lg:py-16"
        >
            <section
                id="overview"
                class="grid items-end gap-10 lg:grid-cols-[1.35fr_0.65fr]"
            >
                <div class="max-w-4xl">
                    <div
                        class="mb-5 inline-flex items-center gap-2 rounded-full border border-emerald-300/15 bg-emerald-300/7 px-3 py-1.5 text-xs font-medium tracking-[0.16em] text-emerald-200 uppercase"
                    >
                        <Activity class="h-3.5 w-3.5" />
                        Прогноз волатильности · 24 часа
                    </div>
                    <h1
                        class="max-w-3xl text-4xl leading-[1.05] font-semibold tracking-[-0.045em] text-balance text-white sm:text-6xl lg:text-7xl"
                    >
                        Сначала данные.
                        <span class="text-slate-500">Потом прогноз.</span>
                    </h1>
                    <p
                        class="mt-6 max-w-2xl text-base leading-7 text-slate-400 sm:text-lg"
                    >
                        Oracle уже измеряет фактическую 24-часовую волатильность
                        BTC, ETH и SOL и показывает вклад каждого фактора риска.
                        Persistence baseline сравнивается с прозрачным
                        GDELT-кандидатом: новости влияют на прогноз только после
                        завершения календарного дня.
                    </p>
                </div>

                <div
                    class="rounded-2xl border border-white/10 bg-white/[0.035] p-5 shadow-2xl shadow-black/20"
                >
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p
                                class="text-xs tracking-[0.14em] text-slate-500 uppercase"
                            >
                                Состояние контура
                            </p>
                            <p class="mt-2 text-lg font-medium text-white">
                                {{
                                    overview.data_state === 'ready'
                                        ? 'Рыночные данные получены'
                                        : 'Ожидает первого сбора'
                                }}
                            </p>
                        </div>
                        <span
                            :class="[
                                'grid h-11 w-11 place-items-center rounded-xl',
                                overview.data_state === 'ready'
                                    ? 'bg-emerald-300/10 text-emerald-300'
                                    : 'bg-amber-300/10 text-amber-300',
                            ]"
                        >
                            <CheckCircle2
                                v-if="overview.data_state === 'ready'"
                                class="h-5 w-5"
                            />
                            <Clock3 v-else class="h-5 w-5" />
                        </span>
                    </div>
                    <div
                        class="mt-5 h-1.5 overflow-hidden rounded-full bg-white/6"
                    >
                        <div
                            :class="[
                                'h-full rounded-full bg-gradient-to-r from-amber-300 to-emerald-300',
                                overview.data_state === 'ready'
                                    ? 'w-1/2'
                                    : 'w-1/4',
                            ]"
                        />
                    </div>
                    <div
                        class="mt-5 grid grid-cols-3 gap-3 border-t border-white/8 pt-5"
                    >
                        <div>
                            <p class="text-xl font-semibold text-white">
                                {{ overview.assets_count }}
                            </p>
                            <p class="mt-1 text-xs text-slate-500">актива</p>
                        </div>
                        <div>
                            <p class="text-xl font-semibold text-white">
                                {{ overview.sources_count }}
                            </p>
                            <p class="mt-1 text-xs text-slate-500">источника</p>
                        </div>
                        <div>
                            <p class="text-xl font-semibold text-white">
                                {{ overview.forecast_horizon_hours }}ч
                            </p>
                            <p class="mt-1 text-xs text-slate-500">горизонт</p>
                        </div>
                    </div>
                </div>
            </section>

            <section aria-labelledby="assets-title">
                <div
                    class="mb-6 flex flex-col justify-between gap-3 sm:flex-row sm:items-end"
                >
                    <div>
                        <p
                            class="text-xs font-medium tracking-[0.16em] text-emerald-300 uppercase"
                        >
                            Наблюдаемые рынки
                        </p>
                        <h2
                            id="assets-title"
                            class="mt-2 text-2xl font-semibold tracking-tight text-white sm:text-3xl"
                        >
                            Рыночный риск сейчас
                        </h2>
                    </div>
                    <p class="max-w-md text-sm leading-6 text-slate-500">
                        Score — не вероятность и не торговый сигнал. Это
                        воспроизводимая оценка наблюдаемого движения за полные
                        24 часа.
                    </p>
                </div>

                <div
                    class="grid items-start gap-5 md:grid-cols-2 xl:grid-cols-3"
                >
                    <article
                        v-for="asset in assets"
                        :key="asset.id"
                        class="group relative overflow-hidden rounded-2xl border border-white/10 bg-[#0c1116] p-5 transition duration-300 hover:-translate-y-1 hover:border-white/20"
                    >
                        <div
                            class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-white/35 to-transparent opacity-60"
                        />
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex items-center gap-3">
                                <span
                                    :class="[
                                        'grid h-11 w-11 place-items-center rounded-xl bg-gradient-to-br text-sm font-bold text-slate-950',
                                        assetAccents[asset.base_currency] ??
                                            'from-slate-200 to-slate-500',
                                    ]"
                                >
                                    {{ asset.base_currency.slice(0, 1) }}
                                </span>
                                <div>
                                    <h3 class="font-semibold text-white">
                                        {{ asset.name }}
                                    </h3>
                                    <p
                                        class="mt-0.5 text-xs tracking-[0.12em] text-slate-500 uppercase"
                                    >
                                        {{ asset.symbol }}
                                    </p>
                                </div>
                            </div>
                            <span
                                :class="[
                                    'flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-medium',
                                    asset.market.last_price === null
                                        ? 'bg-amber-300/8 text-amber-200'
                                        : 'bg-emerald-300/8 text-emerald-200',
                                ]"
                            >
                                <span
                                    :class="[
                                        'h-1.5 w-1.5 rounded-full',
                                        asset.market.last_price === null
                                            ? 'bg-amber-300'
                                            : 'bg-emerald-300',
                                    ]"
                                />
                                {{
                                    asset.market.last_price === null
                                        ? 'ожидание'
                                        : 'данные получены'
                                }}
                            </span>
                        </div>

                        <div class="mt-9">
                            <p
                                class="text-xs tracking-[0.14em] text-slate-600 uppercase"
                            >
                                Последняя цена
                            </p>
                            <div class="mt-2 flex items-baseline gap-2">
                                <span
                                    class="text-4xl font-semibold tracking-tight text-slate-200"
                                    >{{
                                        formatPrice(asset.market.last_price)
                                    }}</span
                                >
                                <span class="text-sm text-slate-600">{{
                                    asset.quote_currency
                                }}</span>
                            </div>
                            <p class="mt-3 text-xs leading-5 text-slate-600">
                                {{
                                    asset.market.updated_at === null
                                        ? 'Котировка появится после первой синхронизации Bybit.'
                                        : `Часовая свеча Bybit · ${formatDateTime(asset.market.updated_at)} МСК`
                                }}
                            </p>
                        </div>

                        <div
                            class="mt-7 rounded-xl border border-white/7 bg-black/20 p-4"
                        >
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <div
                                    class="flex items-center gap-2 text-sm text-slate-400"
                                >
                                    <Gauge class="h-4 w-4 text-emerald-300" />
                                    Наблюдаемый риск · 24ч
                                </div>
                                <span
                                    :class="[
                                        'rounded-full border px-2 py-0.5 text-xs font-medium',
                                        riskTone(asset.analysis.risk_level),
                                    ]"
                                >
                                    {{
                                        asset.analysis.risk_score === null
                                            ? 'нет score'
                                            : `${asset.analysis.risk_score}/100`
                                    }}
                                </span>
                            </div>

                            <template v-if="asset.analysis.status === 'ready'">
                                <div class="mt-4 grid grid-cols-3 gap-2">
                                    <div
                                        class="rounded-lg bg-white/[0.035] p-2.5"
                                    >
                                        <p
                                            class="text-[10px] tracking-wide text-slate-600 uppercase"
                                        >
                                            Цена 24ч
                                        </p>
                                        <p
                                            :class="[
                                                'mt-1.5 text-sm font-semibold',
                                                (asset.market.change_24h ??
                                                    0) >= 0
                                                    ? 'text-emerald-300'
                                                    : 'text-rose-300',
                                            ]"
                                        >
                                            {{
                                                formatPercent(
                                                    asset.market.change_24h,
                                                    true,
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <div
                                        class="rounded-lg bg-white/[0.035] p-2.5"
                                    >
                                        <p
                                            class="text-[10px] tracking-wide text-slate-600 uppercase"
                                        >
                                            RV 24ч
                                        </p>
                                        <p
                                            class="mt-1.5 text-sm font-semibold text-white"
                                        >
                                            {{
                                                formatPercent(
                                                    asset.market.volatility_24h,
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <div
                                        class="rounded-lg bg-white/[0.035] p-2.5"
                                    >
                                        <p
                                            class="text-[10px] tracking-wide text-slate-600 uppercase"
                                        >
                                            Полнота
                                        </p>
                                        <p
                                            class="mt-1.5 text-sm font-semibold text-white"
                                        >
                                            {{
                                                asset.analysis
                                                    .data_completeness
                                            }}%
                                        </p>
                                    </div>
                                </div>

                                <div
                                    class="mt-4 flex items-center justify-between gap-3"
                                >
                                    <span class="text-xs text-slate-500"
                                        >Уровень риска</span
                                    >
                                    <span
                                        :class="[
                                            'rounded-full border px-2 py-0.5 text-xs font-medium',
                                            riskTone(asset.analysis.risk_level),
                                        ]"
                                    >
                                        {{ asset.analysis.risk_label }}
                                    </span>
                                </div>

                                <div class="mt-4 grid gap-3">
                                    <div
                                        v-for="factor in asset.analysis.factors"
                                        :key="factor.key"
                                        :title="factor.explanation"
                                    >
                                        <div
                                            class="flex items-center justify-between gap-3 text-[11px]"
                                        >
                                            <span
                                                class="truncate text-slate-500"
                                            >
                                                {{ factor.label }}
                                            </span>
                                            <span
                                                class="shrink-0 text-slate-400"
                                            >
                                                +{{ factor.contribution }} /
                                                {{ factor.max_contribution }}
                                            </span>
                                        </div>
                                        <div
                                            class="mt-1.5 h-1 overflow-hidden rounded-full bg-white/6"
                                        >
                                            <div
                                                class="h-full rounded-full bg-emerald-300/70"
                                                :style="{
                                                    width: `${(factor.contribution / factor.max_contribution) * 100}%`,
                                                }"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <div v-else class="mt-4">
                                <div
                                    class="flex items-center justify-between gap-3 text-xs"
                                >
                                    <span class="text-slate-500"
                                        >Полнота часового ряда</span
                                    >
                                    <span class="text-amber-200">
                                        {{ asset.analysis.data_completeness }}%
                                    </span>
                                </div>
                                <div
                                    class="mt-2 h-1.5 overflow-hidden rounded-full bg-white/6"
                                >
                                    <div
                                        class="h-full rounded-full bg-amber-300/70"
                                        :style="{
                                            width: `${asset.analysis.data_completeness}%`,
                                        }"
                                    />
                                </div>
                                <p
                                    class="mt-3 text-xs leading-5 text-slate-600"
                                >
                                    Score появится только при наличии 24
                                    последовательных часовых доходностей.
                                </p>
                            </div>

                            <div class="mt-4 border-t border-white/7 pt-4">
                                <div
                                    v-if="asset.forecast.status === 'ready'"
                                    class="grid gap-3"
                                >
                                    <div
                                        class="flex items-center justify-between gap-3"
                                    >
                                        <div
                                            class="flex items-center gap-2 text-sm text-slate-400"
                                        >
                                            <Waves
                                                class="h-4 w-4 text-cyan-300"
                                            />
                                            Прогноз RV · 24ч
                                        </div>
                                        <span
                                            :class="[
                                                'rounded-full border px-2 py-0.5 text-xs font-medium',
                                                riskTone(asset.forecast.risk),
                                            ]"
                                        >
                                            Риск:
                                            {{ asset.forecast.risk_label }}
                                        </span>
                                    </div>

                                    <p class="text-xs text-slate-500">
                                        Модель: {{ asset.forecast.label }}
                                    </p>

                                    <div class="grid grid-cols-2 gap-2">
                                        <div
                                            class="rounded-lg border border-cyan-300/10 bg-cyan-300/[0.035] p-3"
                                        >
                                            <p
                                                class="text-[10px] tracking-wide text-cyan-200/60 uppercase"
                                            >
                                                Ожидаемая RV
                                            </p>
                                            <p
                                                class="mt-1.5 text-xl font-semibold text-cyan-100"
                                            >
                                                {{
                                                    formatPercent(
                                                        asset.forecast
                                                            .predicted_volatility,
                                                    )
                                                }}
                                            </p>
                                        </div>
                                        <div
                                            class="rounded-lg bg-white/[0.035] p-3"
                                        >
                                            <p
                                                class="text-[10px] tracking-wide text-slate-600 uppercase"
                                            >
                                                Диапазон ошибки
                                            </p>
                                            <p
                                                class="mt-1.5 text-sm font-semibold text-slate-200"
                                            >
                                                {{
                                                    formatPercent(
                                                        asset.forecast
                                                            .lower_bound,
                                                    )
                                                }}
                                                —
                                                {{
                                                    formatPercent(
                                                        asset.forecast
                                                            .upper_bound,
                                                    )
                                                }}
                                            </p>
                                        </div>
                                    </div>

                                    <div>
                                        <div
                                            class="flex items-center justify-between gap-3 text-xs"
                                        >
                                            <span class="text-slate-600"
                                                >Надёжность baseline</span
                                            >
                                            <span class="text-slate-300">
                                                {{
                                                    asset.forecast
                                                        .confidence === null
                                                        ? '—'
                                                        : `${asset.forecast.confidence.toFixed(0)}%`
                                                }}
                                            </span>
                                        </div>
                                        <div
                                            class="mt-2 h-1 overflow-hidden rounded-full bg-white/6"
                                        >
                                            <div
                                                class="h-full rounded-full bg-cyan-300/70"
                                                :style="{
                                                    width: `${asset.forecast.confidence ?? 0}%`,
                                                }"
                                            />
                                        </div>
                                    </div>

                                    <div
                                        class="grid grid-cols-3 gap-2 border-t border-white/7 pt-4 text-center"
                                    >
                                        <div>
                                            <p
                                                class="text-sm font-semibold text-white"
                                            >
                                                {{
                                                    asset.forecast.backtest
                                                        .sample_size
                                                }}
                                            </p>
                                            <p
                                                class="mt-1 text-[10px] text-slate-600 uppercase"
                                            >
                                                прогнозов
                                            </p>
                                        </div>
                                        <div>
                                            <p
                                                class="text-sm font-semibold text-white"
                                            >
                                                {{
                                                    formatPercent(
                                                        asset.forecast.backtest
                                                            .mae,
                                                    )
                                                }}
                                            </p>
                                            <p
                                                class="mt-1 text-[10px] text-slate-600 uppercase"
                                            >
                                                MAE
                                            </p>
                                        </div>
                                        <div>
                                            <p
                                                class="text-sm font-semibold text-white"
                                            >
                                                {{
                                                    formatPercent(
                                                        asset.forecast.backtest
                                                            .rmse,
                                                    )
                                                }}
                                            </p>
                                            <p
                                                class="mt-1 text-[10px] text-slate-600 uppercase"
                                            >
                                                RMSE
                                            </p>
                                        </div>
                                    </div>

                                    <p
                                        class="text-[11px] leading-5 text-slate-600"
                                    >
                                        Цель:
                                        {{
                                            formatDateTime(
                                                asset.forecast.target_at,
                                            )
                                        }}
                                        МСК ·
                                        {{ asset.forecast.model_version }}
                                    </p>

                                    <div
                                        class="rounded-xl border border-violet-300/10 bg-violet-300/[0.035] p-3"
                                    >
                                        <div
                                            class="flex items-center justify-between gap-3"
                                        >
                                            <div
                                                class="flex items-center gap-2 text-xs font-medium text-violet-200"
                                            >
                                                <Newspaper
                                                    class="h-3.5 w-3.5"
                                                />
                                                GDELT candidate
                                            </div>
                                            <span
                                                :class="[
                                                    'rounded-full border px-2 py-0.5 text-[10px] font-medium',
                                                    asset.model_comparison
                                                        .verdict ===
                                                    'candidate_better'
                                                        ? 'border-emerald-300/20 bg-emerald-300/8 text-emerald-200'
                                                        : asset.model_comparison
                                                                .verdict ===
                                                            'baseline_better'
                                                          ? 'border-amber-300/20 bg-amber-300/8 text-amber-200'
                                                          : 'border-white/10 bg-white/5 text-slate-400',
                                                ]"
                                            >
                                                {{
                                                    modelVerdictLabels[
                                                        asset.model_comparison
                                                            .verdict
                                                    ]
                                                }}
                                            </span>
                                        </div>

                                        <template
                                            v-if="
                                                asset.model_comparison
                                                    .status === 'ready'
                                            "
                                        >
                                            <div
                                                class="mt-3 grid grid-cols-3 gap-2 text-center"
                                            >
                                                <div>
                                                    <p
                                                        class="text-sm font-semibold text-violet-100"
                                                    >
                                                        {{
                                                            formatPercent(
                                                                asset
                                                                    .model_comparison
                                                                    .candidate
                                                                    .predicted_volatility,
                                                            )
                                                        }}
                                                    </p>
                                                    <p
                                                        class="mt-1 text-[9px] text-slate-600 uppercase"
                                                    >
                                                        RV
                                                    </p>
                                                </div>
                                                <div>
                                                    <p
                                                        class="text-sm font-semibold text-white"
                                                    >
                                                        {{
                                                            formatPercent(
                                                                asset
                                                                    .model_comparison
                                                                    .candidate
                                                                    .mae,
                                                            )
                                                        }}
                                                    </p>
                                                    <p
                                                        class="mt-1 text-[9px] text-slate-600 uppercase"
                                                    >
                                                        MAE
                                                    </p>
                                                </div>
                                                <div>
                                                    <p
                                                        class="text-sm font-semibold text-white"
                                                    >
                                                        {{
                                                            asset
                                                                .model_comparison
                                                                .candidate
                                                                .sample_size
                                                        }}
                                                    </p>
                                                    <p
                                                        class="mt-1 text-[9px] text-slate-600 uppercase"
                                                    >
                                                        окон
                                                    </p>
                                                </div>
                                            </div>

                                            <div
                                                v-if="
                                                    asset.model_comparison
                                                        .candidate.news_inputs
                                                "
                                                class="mt-3 border-t border-white/7 pt-3 text-[10px] leading-5 text-slate-500"
                                            >
                                                {{
                                                    asset.model_comparison
                                                        .candidate.news_inputs
                                                        .article_count
                                                }}
                                                статей · tone
                                                {{
                                                    asset.model_comparison.candidate.news_inputs.average_tone.toFixed(
                                                        2,
                                                    )
                                                }}
                                                · множитель ×{{
                                                    asset.model_comparison.candidate.news_inputs.multiplier.toFixed(
                                                        3,
                                                    )
                                                }}
                                                <span
                                                    class="block text-slate-700"
                                                >
                                                    Только завершённый день
                                                    {{
                                                        asset.model_comparison
                                                            .candidate
                                                            .news_inputs
                                                            .feature_date
                                                    }}
                                                </span>
                                            </div>
                                        </template>

                                        <p
                                            v-else
                                            class="mt-3 text-[10px] leading-5 text-slate-600"
                                        >
                                            Сравнение появится после загрузки
                                            дневной истории GDELT.
                                        </p>
                                    </div>

                                    <details
                                        class="group overflow-hidden rounded-xl border border-emerald-300/10 bg-emerald-300/[0.025]"
                                    >
                                        <summary
                                            class="flex cursor-pointer list-none items-center justify-between gap-3 p-3 transition hover:bg-emerald-300/[0.035] focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-emerald-300/60 [&::-webkit-details-marker]:hidden"
                                        >
                                            <span
                                                class="flex items-center gap-2 text-xs font-medium text-emerald-200"
                                            >
                                                <CheckCircle2
                                                    class="h-3.5 w-3.5"
                                                />
                                                Аргументы прогноза
                                            </span>
                                            <span
                                                class="flex shrink-0 items-center gap-2 text-[10px] text-slate-500"
                                            >
                                                Факторов:
                                                {{
                                                    asset.forecast.arguments
                                                        .length
                                                }}
                                                <ChevronDown
                                                    class="h-3.5 w-3.5 transition-transform duration-200 group-open:rotate-180"
                                                />
                                            </span>
                                        </summary>
                                        <ul
                                            class="grid gap-2 border-t border-emerald-300/10 p-3"
                                        >
                                            <li
                                                v-for="argument in asset
                                                    .forecast.arguments"
                                                :key="argument"
                                                class="flex gap-2 text-[11px] leading-5 text-slate-400"
                                            >
                                                <span
                                                    class="mt-2 h-1 w-1 shrink-0 rounded-full bg-emerald-300/70"
                                                />
                                                {{ argument }}
                                            </li>
                                        </ul>
                                    </details>

                                    <details
                                        class="group overflow-hidden rounded-xl border border-amber-300/10 bg-amber-300/[0.025]"
                                    >
                                        <summary
                                            class="flex cursor-pointer list-none items-center justify-between gap-3 p-3 transition hover:bg-amber-300/[0.035] focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-amber-300/60 [&::-webkit-details-marker]:hidden"
                                        >
                                            <span
                                                class="flex items-center gap-2 text-xs font-medium text-amber-200"
                                            >
                                                <TriangleAlert
                                                    class="h-3.5 w-3.5 shrink-0"
                                                />
                                                Почему прогноз может ошибиться
                                            </span>
                                            <span
                                                class="flex shrink-0 items-center gap-2 text-[10px] text-slate-500"
                                            >
                                                Рисков:
                                                {{
                                                    asset.forecast.failure_risks
                                                        .length
                                                }}
                                                <ChevronDown
                                                    class="h-3.5 w-3.5 transition-transform duration-200 group-open:rotate-180"
                                                />
                                            </span>
                                        </summary>
                                        <ul
                                            class="grid gap-2 border-t border-amber-300/10 p-3"
                                        >
                                            <li
                                                v-for="risk in asset.forecast
                                                    .failure_risks"
                                                :key="risk"
                                                class="flex gap-2 text-[11px] leading-5 text-slate-400"
                                            >
                                                <span
                                                    class="mt-2 h-1 w-1 shrink-0 rounded-full bg-amber-300/70"
                                                />
                                                {{ risk }}
                                            </li>
                                        </ul>
                                    </details>
                                </div>

                                <div
                                    v-else
                                    class="flex items-center justify-between gap-3"
                                >
                                    <span class="text-xs text-slate-600"
                                        >Прогноз</span
                                    >
                                    <span class="text-xs text-slate-400">
                                        {{ asset.forecast.label }}
                                    </span>
                                </div>
                            </div>

                            <div class="mt-4 flex flex-wrap gap-2">
                                <span
                                    v-for="source in asset.sources"
                                    :key="source.key"
                                    class="rounded-md border border-white/8 bg-white/4 px-2 py-1 text-[10px] font-medium tracking-[0.1em] text-slate-500 uppercase"
                                >
                                    {{ source.key }}
                                </span>
                            </div>
                        </div>
                    </article>
                </div>
            </section>

            <section id="sources" class="grid gap-6 lg:grid-cols-[0.8fr_1.2fr]">
                <div
                    class="rounded-2xl border border-white/10 bg-white/[0.025] p-6 sm:p-8"
                >
                    <span
                        class="grid h-11 w-11 place-items-center rounded-xl bg-cyan-300/10 text-cyan-300"
                    >
                        <Database class="h-5 w-5" />
                    </span>
                    <p
                        class="mt-6 text-xs font-medium tracking-[0.16em] text-cyan-300 uppercase"
                    >
                        Контур данных
                    </p>
                    <h2
                        class="mt-2 text-3xl font-semibold tracking-tight text-white"
                    >
                        Источники видны пользователю
                    </h2>
                    <p class="mt-4 text-sm leading-6 text-slate-500">
                        Для каждого расчёта Oracle сохранит исходные записи,
                        время загрузки и результат нормализации. Если источник
                        недоступен, уверенность будет снижена.
                    </p>
                </div>

                <div class="grid gap-4">
                    <article
                        v-for="source in sources"
                        :key="source.id"
                        class="flex flex-col justify-between gap-5 rounded-2xl border border-white/10 bg-[#0c1116] p-5 sm:flex-row sm:items-center"
                    >
                        <div class="flex items-start gap-4">
                            <span
                                class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-white/5 text-slate-300"
                            >
                                <Radio
                                    v-if="source.type === 'market'"
                                    class="h-4 w-4"
                                />
                                <Newspaper v-else class="h-4 w-4" />
                            </span>
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="font-semibold text-white">
                                        {{ source.name }}
                                    </h3>
                                    <span
                                        class="rounded-full border border-white/8 px-2 py-0.5 text-[10px] tracking-[0.1em] text-slate-500 uppercase"
                                    >
                                        {{ sourceTypeLabels[source.type] }}
                                    </span>
                                </div>
                                <p
                                    class="mt-2 max-w-lg truncate text-xs text-slate-600"
                                >
                                    {{ source.base_url }}
                                </p>
                            </div>
                        </div>
                        <div
                            class="flex items-center justify-between gap-4 sm:justify-end"
                        >
                            <div class="text-right">
                                <p class="text-xs text-slate-600">
                                    Последние загруженные данные
                                </p>
                                <p class="mt-1 text-sm text-slate-400">
                                    {{
                                        source.latest_sync === null
                                            ? 'Данные ещё не загружены'
                                            : `${formatDateTime(source.latest_sync.finished_at ?? source.latest_sync.started_at)} · ${source.latest_sync.records_stored} записей`
                                    }}
                                </p>
                                <p
                                    v-if="
                                        source.latest_attempt !== null &&
                                        source.latest_attempt.status !==
                                            'completed'
                                    "
                                    class="mt-1 text-[11px] text-rose-300/70"
                                >
                                    {{ sourceStatusLabels[source.status] }} ·
                                    {{
                                        formatDateTime(
                                            source.latest_attempt.finished_at ??
                                                source.latest_attempt
                                                    .started_at,
                                        )
                                    }}
                                </p>
                            </div>
                            <span
                                :class="[
                                    'h-2 w-2 rounded-full',
                                    source.status === 'online'
                                        ? 'bg-emerald-300 shadow-[0_0_12px_rgba(110,231,183,0.45)]'
                                        : source.status === 'error'
                                          ? 'bg-rose-400 shadow-[0_0_12px_rgba(251,113,133,0.45)]'
                                          : 'bg-amber-300 shadow-[0_0_12px_rgba(252,211,77,0.45)]',
                                ]"
                            />
                        </div>
                    </article>
                </div>
            </section>

            <section
                id="methodology"
                class="rounded-3xl border border-white/10 bg-[#0b0f13] p-6 sm:p-8 lg:p-10"
            >
                <div class="grid gap-10 lg:grid-cols-[0.7fr_1.3fr]">
                    <div>
                        <div
                            class="flex items-center gap-2 text-sm font-medium text-emerald-300"
                        >
                            <FlaskConical class="h-4 w-4" />
                            Методика
                        </div>
                        <h2
                            class="mt-4 text-3xl font-semibold tracking-tight text-white"
                        >
                            Расчёт можно повторить
                        </h2>
                        <p class="mt-4 text-sm leading-6 text-slate-500">
                            Baseline переносит измеренную RV последних 24 часов
                            на следующие 24. GDELT-кандидат повышает её при
                            всплеске внимания и негативном tone. В backtest обе
                            модели используют только известное прошлое и
                            сравниваются по MAE и RMSE.
                        </p>
                    </div>

                    <ol
                        class="grid gap-px overflow-hidden rounded-2xl border border-white/8 bg-white/8 sm:grid-cols-2"
                    >
                        <li
                            v-for="(step, index) in [
                                [
                                    'Сбор',
                                    'Публичные часовые OHLCV-свечи Bybit сохраняются в MySQL.',
                                ],
                                [
                                    'Контроль ряда',
                                    'Расчёт блокируется, если среди 24 доходностей есть пропуск.',
                                ],
                                [
                                    'Risk score',
                                    'Пользователь видит значение и вклад каждого фактора.',
                                ],
                                [
                                    'Backtest',
                                    'Baseline и news candidate сравниваются на одинаковых будущих окнах без утечки данных.',
                                ],
                            ]"
                            :key="step[0]"
                            class="bg-[#0b0f13] p-5"
                        >
                            <div
                                class="flex items-center justify-between gap-4"
                            >
                                <span
                                    class="text-xs font-semibold text-emerald-300"
                                    >0{{ index + 1 }}</span
                                >
                                <CheckCircle2 class="h-4 w-4 text-slate-700" />
                            </div>
                            <h3 class="mt-5 font-medium text-white">
                                {{ step[0] }}
                            </h3>
                            <p class="mt-2 text-xs leading-5 text-slate-500">
                                {{ step[1] }}
                            </p>
                        </li>
                    </ol>
                </div>

                <div
                    class="mt-8 grid gap-4 border-t border-white/8 pt-8 md:grid-cols-3"
                >
                    <div class="flex gap-3">
                        <ShieldCheck
                            class="mt-0.5 h-4 w-4 shrink-0 text-emerald-300"
                        />
                        <div>
                            <p class="text-sm font-medium text-slate-300">
                                Без торговых операций
                            </p>
                            <p class="mt-1 text-xs leading-5 text-slate-600">
                                Проект анализирует данные и не управляет
                                деньгами.
                            </p>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <ArrowDownRight
                            class="mt-0.5 h-4 w-4 shrink-0 text-emerald-300"
                        />
                        <div>
                            <p class="text-sm font-medium text-slate-300">
                                Ошибки сохраняются
                            </p>
                            <p class="mt-1 text-xs leading-5 text-slate-600">
                                Неудачный прогноз остаётся частью статистики.
                            </p>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <Activity
                            class="mt-0.5 h-4 w-4 shrink-0 text-emerald-300"
                        />
                        <div>
                            <p class="text-sm font-medium text-slate-300">
                                Никаких случайных ответов
                            </p>
                            <p class="mt-1 text-xs leading-5 text-slate-600">
                                Пока данных нет, система честно показывает
                                ожидание.
                            </p>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <footer class="relative border-t border-white/8">
            <div
                class="mx-auto flex max-w-7xl flex-col gap-2 px-5 py-7 text-xs text-slate-600 sm:flex-row sm:items-center sm:justify-between sm:px-8 lg:px-10"
            >
                <span>Crypto Volatility Oracle · Research prototype</span>
                <span>Данные не являются инвестиционной рекомендацией</span>
            </div>
        </footer>
    </div>
</template>
