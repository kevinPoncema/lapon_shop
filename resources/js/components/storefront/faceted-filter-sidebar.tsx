import {
    Check,
    ChevronLeft,
    ChevronRight,
    RotateCcw,
    SlidersHorizontal,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { type CategoryNode, type StorefrontFilterItem } from '@/types/commerce';

export interface ActiveCatalogFilters {
    categorySlug: string;
    priceMin: string;
    priceMax: string;
    inStock: boolean;
    attr: Record<string, string | string[]>;
}

interface FacetedFilterSidebarProps {
    categories: CategoryNode[];
    filters: StorefrontFilterItem[];
    value: ActiveCatalogFilters;
    onChange: (next: ActiveCatalogFilters) => void;
    onReset: () => void;
    currencyCode: string;
    facetPageSize?: number;
}

export function FacetedFilterSidebar({
    categories,
    filters,
    value,
    onChange,
    onReset,
    currencyCode,
    facetPageSize = 6,
}: FacetedFilterSidebarProps) {
    const sortedFilters = [...filters].sort((a, b) => a.order - b.order);
    const hasPriceFilter = sortedFilters.some(
        (f) => f.type === 'system' && f.key === 'price',
    );
    const metadataFilters = sortedFilters.filter(
        (f): f is Extract<StorefrontFilterItem, { type: 'metadata' }> =>
            f.type === 'metadata',
    );

    const effectivePageSize = Math.max(1, facetPageSize);
    const [facetPage, setFacetPage] = useState<number>(1);
    const totalFacetPages = Math.max(
        1,
        Math.ceil(metadataFilters.length / effectivePageSize),
    );

    useEffect(() => {
        if (facetPage > totalFacetPages) {
            setFacetPage(totalFacetPages);
        }
    }, [metadataFilters.length, facetPage, totalFacetPages]);

    const visibleMetadataFilters = metadataFilters.slice(
        (facetPage - 1) * effectivePageSize,
        facetPage * effectivePageSize,
    );

    const activeCount =
        (value.categorySlug ? 1 : 0) +
        (value.priceMin ? 1 : 0) +
        (value.priceMax ? 1 : 0) +
        (value.inStock ? 1 : 0) +
        Object.values(value.attr).filter((v) =>
            Array.isArray(v) ? v.length > 0 : String(v).trim().length > 0,
        ).length;

    const toggleMultiOption = (key: string, option: string) => {
        const currentRaw = value.attr[key];
        const currentList = Array.isArray(currentRaw)
            ? currentRaw
            : currentRaw
              ? [currentRaw]
              : [];

        const exists = currentList.includes(option);
        const nextList = exists
            ? currentList.filter((item) => item !== option)
            : [...currentList, option];

        const nextAttr = { ...value.attr };
        if (nextList.length === 0) {
            delete nextAttr[key];
        } else {
            nextAttr[key] = nextList;
        }

        onChange({ ...value, attr: nextAttr });
    };

    const setSingleAttr = (key: string, nextVal: string) => {
        const nextAttr = { ...value.attr };
        if (nextVal.trim() === '') {
            delete nextAttr[key];
        } else {
            nextAttr[key] = nextVal;
        }
        onChange({ ...value, attr: nextAttr });
    };

    return (
        <aside className="space-y-6">
            <div className="flex items-center justify-between border-b border-zinc-800/80 pb-3">
                <div className="flex items-center gap-2 text-xs font-semibold tracking-wider text-zinc-200 uppercase">
                    <SlidersHorizontal className="size-3.5 text-primary" />
                    <span>Filtros Dinámicos</span>
                    {activeCount > 0 && (
                        <span className="rounded-full bg-primary/20 px-1.5 py-0.5 font-mono text-[10px] text-primary">
                            {activeCount}
                        </span>
                    )}
                </div>

                {activeCount > 0 && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={onReset}
                        className="h-6 px-2 text-[11px] text-zinc-400 hover:text-zinc-100"
                    >
                        <RotateCcw className="mr-1 size-3" />
                        Limpiar
                    </Button>
                )}
            </div>

            {/* Disponibilidad inmediata */}
            <div className="rounded-xl border border-zinc-800/90 bg-[#121215] p-3.5">
                <button
                    type="button"
                    onClick={() =>
                        onChange({ ...value, inStock: !value.inStock })
                    }
                    className="flex w-full items-center justify-between text-left"
                >
                    <div>
                        <p className="text-xs font-medium text-zinc-100">
                            Stock disponible hoy
                        </p>
                        <p className="text-[11px] text-zinc-500">
                            Ocultar variantes agotadas
                        </p>
                    </div>
                    <span
                        className={`inline-flex h-5 w-9 items-center rounded-full border transition ${
                            value.inStock
                                ? 'justify-end border-emerald-500/60 bg-emerald-500/20 px-0.5'
                                : 'justify-start border-zinc-700 bg-zinc-900 px-0.5'
                        }`}
                    >
                        <span
                            className={`size-3.5 rounded-full transition ${
                                value.inStock ? 'bg-emerald-400' : 'bg-zinc-500'
                            }`}
                        />
                    </span>
                </button>
            </div>

            {/* Categorías Jerárquicas */}
            {categories.length > 0 && (
                <div className="space-y-2.5">
                    <h3 className="text-[11px] font-semibold tracking-wider text-zinc-400 uppercase">
                        Categorías
                    </h3>
                    <div className="space-y-1">
                        <button
                            type="button"
                            onClick={() =>
                                onChange({ ...value, categorySlug: '' })
                            }
                            className={`flex w-full items-center justify-between rounded-lg px-2.5 py-1.5 text-xs transition ${
                                value.categorySlug === ''
                                    ? 'bg-primary/15 font-medium text-zinc-50 ring-1 ring-primary/40'
                                    : 'text-zinc-400 hover:bg-zinc-900 hover:text-zinc-200'
                            }`}
                        >
                            <span>Todas las colecciones</span>
                        </button>

                        {categories.map((cat) => (
                            <div key={cat.id} className="space-y-1">
                                <button
                                    type="button"
                                    onClick={() =>
                                        onChange({
                                            ...value,
                                            categorySlug:
                                                value.categorySlug === cat.slug
                                                    ? ''
                                                    : cat.slug,
                                        })
                                    }
                                    className={`flex w-full items-center justify-between rounded-lg px-2.5 py-1.5 text-xs transition ${
                                        value.categorySlug === cat.slug
                                            ? 'bg-primary/15 font-medium text-zinc-50 ring-1 ring-primary/40'
                                            : 'text-zinc-300 hover:bg-zinc-900 hover:text-zinc-100'
                                    }`}
                                >
                                    <span>{cat.name}</span>
                                    {value.categorySlug === cat.slug && (
                                        <Check className="size-3 text-primary" />
                                    )}
                                </button>

                                {cat.children && cat.children.length > 0 && (
                                    <div className="ml-3 space-y-0.5 border-l border-zinc-800 pl-2.5">
                                        {cat.children.map((child) => (
                                            <button
                                                key={child.id}
                                                type="button"
                                                onClick={() =>
                                                    onChange({
                                                        ...value,
                                                        categorySlug:
                                                            value.categorySlug ===
                                                            child.slug
                                                                ? ''
                                                                : child.slug,
                                                    })
                                                }
                                                className={`flex w-full items-center justify-between rounded-md px-2 py-1 text-[11px] transition ${
                                                    value.categorySlug ===
                                                    child.slug
                                                        ? 'bg-primary/15 font-medium text-zinc-50'
                                                        : 'text-zinc-400 hover:text-zinc-200'
                                                }`}
                                            >
                                                <span>{child.name}</span>
                                                {value.categorySlug ===
                                                    child.slug && (
                                                    <Check className="size-3 text-primary" />
                                                )}
                                            </button>
                                        ))}
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {/* Rango de Precios (System Filter o Fallback) */}
            {(hasPriceFilter || filters.length === 0) && (
                <div className="space-y-2.5">
                    <h3 className="text-[11px] font-semibold tracking-wider text-zinc-400 uppercase">
                        Rango de Precio ({currencyCode})
                    </h3>
                    <div className="grid grid-cols-2 gap-2">
                        <div>
                            <label className="sr-only">Precio mínimo</label>
                            <Input
                                type="number"
                                min={0}
                                placeholder="Mín"
                                value={value.priceMin}
                                onChange={(e) =>
                                    onChange({
                                        ...value,
                                        priceMin: e.target.value,
                                    })
                                }
                                className="h-8 border-zinc-800 bg-[#121215] font-mono text-xs text-zinc-100"
                            />
                        </div>
                        <div>
                            <label className="sr-only">Precio máximo</label>
                            <Input
                                type="number"
                                min={0}
                                placeholder="Máx"
                                value={value.priceMax}
                                onChange={(e) =>
                                    onChange({
                                        ...value,
                                        priceMax: e.target.value,
                                    })
                                }
                                className="h-8 border-zinc-800 bg-[#121215] font-mono text-xs text-zinc-100"
                            />
                        </div>
                    </div>
                </div>
            )}

            {/* Facetas Schema-Driven Dinámicas (metadata_schema + filter_config paginadas de a 4) */}
            {visibleMetadataFilters.map((facet) => {
                const currentVal = value.attr[facet.key];

                return (
                    <div
                        key={facet.key}
                        className="space-y-2.5 border-t border-zinc-800/70 pt-4"
                    >
                        <div className="flex items-center justify-between">
                            <h3 className="text-[11px] font-semibold tracking-wider text-zinc-300 uppercase">
                                {facet.label}
                            </h3>
                            {facet.affects_stock && (
                                <span className="rounded bg-zinc-800 px-1.5 py-0.5 font-mono text-[9px] text-zinc-400">
                                    VARIANTE
                                </span>
                            )}
                        </div>

                        {(facet.data_type === 'select' ||
                            facet.data_type === 'multiselect') &&
                        facet.options &&
                        facet.options.length > 0 ? (
                            <div className="flex flex-wrap gap-1.5">
                                {facet.options.map((opt) => {
                                    const selected =
                                        facet.data_type === 'multiselect'
                                            ? Array.isArray(currentVal) &&
                                              currentVal.includes(opt)
                                            : currentVal === opt;

                                    return (
                                        <button
                                            key={opt}
                                            type="button"
                                            onClick={() => {
                                                if (
                                                    facet.data_type ===
                                                    'multiselect'
                                                ) {
                                                    toggleMultiOption(
                                                        facet.key,
                                                        opt,
                                                    );
                                                } else {
                                                    setSingleAttr(
                                                        facet.key,
                                                        selected ? '' : opt,
                                                    );
                                                }
                                            }}
                                            className={`rounded-md border px-2.5 py-1 text-xs transition ${
                                                selected
                                                    ? 'border-primary bg-primary/20 font-medium text-zinc-50'
                                                    : 'border-zinc-800 bg-[#121215] text-zinc-400 hover:border-zinc-700 hover:text-zinc-200'
                                            }`}
                                        >
                                            {opt}
                                        </button>
                                    );
                                })}
                            </div>
                        ) : facet.data_type === 'boolean' ? (
                            <div className="flex gap-1.5">
                                {[
                                    { label: 'Todos', val: '' },
                                    { label: 'Sí', val: '1' },
                                    { label: 'No', val: '0' },
                                ].map((btn) => {
                                    const active =
                                        (currentVal ?? '') === btn.val;
                                    return (
                                        <button
                                            key={btn.label}
                                            type="button"
                                            onClick={() =>
                                                setSingleAttr(
                                                    facet.key,
                                                    btn.val,
                                                )
                                            }
                                            className={`flex-1 rounded-md border py-1 text-xs transition ${
                                                active
                                                    ? 'border-primary bg-primary/20 font-medium text-zinc-50'
                                                    : 'border-zinc-800 bg-[#121215] text-zinc-400 hover:text-zinc-200'
                                            }`}
                                        >
                                            {btn.label}
                                        </button>
                                    );
                                })}
                            </div>
                        ) : (
                            <Input
                                type={
                                    facet.data_type === 'number'
                                        ? 'number'
                                        : facet.data_type === 'date'
                                          ? 'date'
                                          : 'text'
                                }
                                placeholder={`Filtrar por ${facet.label.toLowerCase()}...`}
                                value={
                                    typeof currentVal === 'string'
                                        ? currentVal
                                        : ''
                                }
                                onChange={(e) =>
                                    setSingleAttr(facet.key, e.target.value)
                                }
                                className="h-8 border-zinc-800 bg-[#121215] text-xs text-zinc-100"
                            />
                        )}
                    </div>
                );
            })}

            {/* Paginación dinámica en frontend para filtros (solo aparece al exceder 4 filtros) */}
            {metadataFilters.length > effectivePageSize && (
                <div className="flex items-center justify-between border-t border-zinc-800/80 pt-3 text-xs">
                    <span className="font-mono text-[10px] text-zinc-500">
                        {(facetPage - 1) * effectivePageSize + 1}–
                        {Math.min(
                            facetPage * effectivePageSize,
                            metadataFilters.length,
                        )}{' '}
                        de {metadataFilters.length}
                    </span>
                    <div className="flex items-center gap-1.5">
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            disabled={facetPage <= 1}
                            onClick={() =>
                                setFacetPage((p) => Math.max(1, p - 1))
                            }
                            className="size-7 border-zinc-800 bg-[#121215] text-zinc-300 hover:bg-zinc-900 disabled:opacity-30"
                            title="Filtros anteriores"
                        >
                            <ChevronLeft className="size-3.5" />
                            <span className="sr-only">Anterior</span>
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            disabled={facetPage >= totalFacetPages}
                            onClick={() =>
                                setFacetPage((p) =>
                                    Math.min(totalFacetPages, p + 1),
                                )
                            }
                            className="size-7 border-zinc-800 bg-[#121215] text-zinc-300 hover:bg-zinc-900 disabled:opacity-30"
                            title="Siguientes filtros"
                        >
                            <ChevronRight className="size-3.5" />
                            <span className="sr-only">Siguiente</span>
                        </Button>
                    </div>
                </div>
            )}
        </aside>
    );
}
