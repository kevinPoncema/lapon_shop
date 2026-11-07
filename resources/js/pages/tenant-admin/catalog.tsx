import { Head } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    Edit3,
    FolderPlus,
    Loader2,
    Minus,
    Package,
    Plus,
    RotateCcw,
    Search,
    SlidersHorizontal,
    Trash2,
    X,
} from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';
import { DynamicMetadataFields } from '@/components/admin/dynamic-metadata-fields';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTenant } from '@/contexts/tenant-context';
import { TenantAdminLayout } from '@/layouts/tenant-admin-layout';
import { ApiClientError, commerceApi } from '@/lib/api-client';
import { formatMoney, formatVariantAttributes } from '@/lib/formatters';
import {
    type CategoryNode,
    type CreateProductVariantInput,
    type FormattedProductDetail,
    type MetadataAttributeDefinition,
    type PaginationMeta,
    type PortalShellPageProps,
} from '@/types/commerce';

function emptyVariantDraft(): CreateProductVariantInput {
    return {
        sku: '',
        price: null,
        stock: 10,
        metadata: {},
        is_default: false,
        is_active: true,
    };
}

function CatalogAdminContent() {
    const { tenantSlug, tenantName, currencyCode } = useTenant();

    const [products, setProducts] = useState<FormattedProductDetail[]>([]);
    const [meta, setMeta] = useState<PaginationMeta | null>(null);
    const [categories, setCategories] = useState<CategoryNode[]>([]);
    const [schema, setSchema] = useState<MetadataAttributeDefinition[]>([]);
    const [isLoading, setIsLoading] = useState<boolean>(true);

    // Paginación y Filtros (UI compacta y optimizada)
    const [page, setPage] = useState<number>(1);
    const [perPage, setPerPage] = useState<number>(10);
    const [searchDraft, setSearchDraft] = useState<string>('');
    const [debouncedSearch, setDebouncedSearch] = useState<string>('');
    const [selectedCategory, setSelectedCategory] = useState<string>('');
    const [selectedStatus, setSelectedStatus] = useState<string>(''); // '' | 'active' | 'inactive'
    const [selectedStock, setSelectedStock] = useState<string>(''); // '' | 'in_stock' | 'out_of_stock'
    const [selectedAttrs, setSelectedAttrs] = useState<Record<string, string>>({});
    const [showAttrFilters, setShowAttrFilters] = useState<boolean>(false);
    const [attrPage, setAttrPage] = useState<number>(1);

    // Paginación de atributos dinámicos (4 por vista con controles < >)
    const ATTR_PAGE_SIZE = 4;
    const totalAttrPages = Math.max(1, Math.ceil(schema.length / ATTR_PAGE_SIZE));
    const visibleAttrs = schema.slice(
        (attrPage - 1) * ATTR_PAGE_SIZE,
        attrPage * ATTR_PAGE_SIZE,
    );

    useEffect(() => {
        if (attrPage > totalAttrPages) {
            setAttrPage(totalAttrPages);
        }
    }, [attrPage, totalAttrPages]);

    // Debounce del buscador general
    useEffect(() => {
        const timer = window.setTimeout(() => {
            setDebouncedSearch(searchDraft.trim());
            setPage(1);
        }, 280);
        return () => window.clearTimeout(timer);
    }, [searchDraft]);

    // Nueva Categoría
    const [catModalOpen, setCatModalOpen] = useState<boolean>(false);
    const [newCatName, setNewCatName] = useState<string>('');
    const [newCatParentId, setNewCatParentId] = useState<string>('');
    const [isSavingCat, setIsSavingCat] = useState<boolean>(false);

    // Crear / Editar Producto
    const [productModalOpen, setProductModalOpen] = useState<boolean>(false);
    const [editingId, setEditingId] = useState<string | null>(null);
    const [prodName, setProdName] = useState<string>('');
    const [prodCategoryId, setProdCategoryId] = useState<string>('');
    const [prodDescription, setProdDescription] = useState<string>('');
    const [prodBasePrice, setProdBasePrice] = useState<string>('0');
    const [prodImagesCsv, setProdImagesCsv] = useState<string>('');
    const [prodIsActive, setProdIsActive] = useState<boolean>(true);
    const [prodMetadata, setProdMetadata] = useState<Record<string, unknown>>(
        {},
    );
    const [variantsDraft, setVariantsDraft] = useState<
        CreateProductVariantInput[]
    >([
        {
            ...emptyVariantDraft(),
            is_default: true,
        },
    ]);
    const [isSavingProduct, setIsSavingProduct] = useState<boolean>(false);

    // Cargar metadatos iniciales (Categorías y Esquema)
    const loadMetadata = useCallback(async () => {
        try {
            const [catRes, configRes] = await Promise.all([
                commerceApi.admin.getCategories(tenantSlug),
                commerceApi.admin.getConfig(tenantSlug),
            ]);
            setCategories(catRes.data);
            setSchema(configRes.metadata_schema ?? []);
        } catch (err) {
            toast.error(
                err instanceof ApiClientError
                    ? err.message
                    : 'Error al cargar metadatos del catálogo.',
            );
        }
    }, [tenantSlug]);

    useEffect(() => {
        void loadMetadata();
    }, [loadMetadata]);

    // Cargar listado de productos paginado con filtros
    const fetchProducts = useCallback(async () => {
        setIsLoading(true);
        try {
            const query: {
                q?: string;
                category_id?: string;
                is_active?: boolean;
                in_stock?: boolean;
                attr?: Record<string, string>;
                page?: number;
                per_page?: number;
            } = {
                page,
                per_page: perPage,
            };

            if (debouncedSearch) {
                query.q = debouncedSearch;
            }
            if (selectedCategory) {
                query.category_id = selectedCategory;
            }
            if (selectedStatus === 'active') {
                query.is_active = true;
            } else if (selectedStatus === 'inactive') {
                query.is_active = false;
            }
            if (selectedStock === 'in_stock') {
                query.in_stock = true;
            } else if (selectedStock === 'out_of_stock') {
                query.in_stock = false;
            }
            if (Object.keys(selectedAttrs).length > 0) {
                query.attr = selectedAttrs;
            }

            const res = await commerceApi.admin.getProducts(tenantSlug, query);
            setProducts(res.data);
            setMeta(res.meta);
        } catch (err) {
            toast.error(
                err instanceof ApiClientError
                    ? err.message
                    : 'Error al cargar los productos del catálogo.',
            );
        } finally {
            setIsLoading(false);
        }
    }, [
        tenantSlug,
        page,
        perPage,
        debouncedSearch,
        selectedCategory,
        selectedStatus,
        selectedStock,
        selectedAttrs,
    ]);

    useEffect(() => {
        void fetchProducts();
    }, [fetchProducts]);

    const handleResetFilters = () => {
        setSearchDraft('');
        setDebouncedSearch('');
        setSelectedCategory('');
        setSelectedStatus('');
        setSelectedStock('');
        setSelectedAttrs({});
        setPage(1);
    };

    const hasActiveFilters = Boolean(
        debouncedSearch ||
            selectedCategory ||
            selectedStatus ||
            selectedStock ||
            Object.keys(selectedAttrs).length > 0,
    );

    const flatCategories = categories.flatMap((root) => [
        { id: root.id, label: root.name },
        ...(root.children ?? []).map((c) => ({
            id: c.id,
            label: `${root.name} › ${c.name}`,
        })),
    ]);

    const openCreateModal = () => {
        setEditingId(null);
        setProdName('');
        setProdCategoryId('');
        setProdDescription('');
        setProdBasePrice('100');
        setProdImagesCsv('');
        setProdIsActive(true);
        setProdMetadata({});
        setVariantsDraft([{ ...emptyVariantDraft(), is_default: true }]);
        setProductModalOpen(true);
    };

    const openEditModal = (product: FormattedProductDetail) => {
        setEditingId(product.id);
        setProdName(product.name);
        setProdCategoryId(product.category_id ?? '');
        setProdDescription(product.description ?? '');
        setProdBasePrice(String(product.base_price));
        setProdImagesCsv(product.images.join(', '));
        setProdIsActive(product.is_active);
        setProdMetadata(product.metadata ?? {});
        setVariantsDraft(
            product.variants.map((v) => ({
                id: v.id,
                sku: v.sku,
                price: v.price_override,
                stock: v.stock,
                metadata: v.metadata ?? {},
                is_default: v.is_default,
                is_active: v.is_active,
            })),
        );
        setProductModalOpen(true);
    };

    const handleSaveCategory = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!newCatName.trim() || isSavingCat) {
            return;
        }
        setIsSavingCat(true);
        try {
            await commerceApi.admin.createCategory(tenantSlug, {
                name: newCatName.trim(),
                parent_id: newCatParentId || null,
                is_active: true,
            });
            toast.success('Categoría creada exitosamente.');
            setNewCatName('');
            setNewCatParentId('');
            setCatModalOpen(false);
            await loadMetadata();
        } catch (err) {
            toast.error(
                err instanceof ApiClientError
                    ? (err.firstFieldError() ?? err.message)
                    : 'No se pudo crear la categoría.',
            );
        } finally {
            setIsSavingCat(false);
        }
    };

    const handleSaveProduct = async (e: React.FormEvent) => {
        e.preventDefault();
        if (isSavingProduct) {
            return;
        }
        setIsSavingProduct(true);

        const images = prodImagesCsv
            .split(',')
            .map((u) => u.trim())
            .filter((u) => u.length > 0);

        const payload = {
            category_id: prodCategoryId || null,
            name: prodName.trim(),
            description: prodDescription.trim() || null,
            base_price: Number(prodBasePrice),
            images,
            metadata: prodMetadata,
            is_active: prodIsActive,
            variants: variantsDraft.map((v, idx) => ({
                ...(v.id ? { id: v.id } : {}),
                sku: v.sku?.trim() ? v.sku.trim() : null,
                price:
                    v.price !== null && v.price !== undefined && v.price > 0
                        ? Number(v.price)
                        : null,
                stock: Math.max(0, Math.floor(Number(v.stock))),
                metadata: v.metadata ?? {},
                is_default: idx === 0 ? true : Boolean(v.is_default),
                is_active: v.is_active ?? true,
            })),
        };

        try {
            if (editingId) {
                await commerceApi.admin.updateProduct(
                    tenantSlug,
                    editingId,
                    payload,
                );
                toast.success('Producto y variantes actualizados.');
            } else {
                await commerceApi.admin.createProduct(tenantSlug, payload);
                toast.success('Producto creado exitosamente.');
            }
            setProductModalOpen(false);
            await fetchProducts();
        } catch (err) {
            toast.error(
                err instanceof ApiClientError
                    ? (err.firstFieldError() ?? err.message)
                    : 'Error al guardar el producto.',
            );
        } finally {
            setIsSavingProduct(false);
        }
    };

    const handleQuickStockDelta = async (variantId: string, delta: number) => {
        try {
            const res = await commerceApi.admin.adjustVariantStock(
                tenantSlug,
                variantId,
                {
                    mode: 'adjust',
                    quantity: delta,
                    reason: 'Ajuste rápido en consola',
                },
            );
            toast.success(
                `Stock ${res.data.sku}: ${res.data.previous_stock} → ${res.data.current_stock}`,
            );
            await fetchProducts();
        } catch (err) {
            toast.error(
                err instanceof ApiClientError
                    ? err.message
                    : 'No se pudo ajustar el stock.',
            );
        }
    };

    const handleToggleVariantStatus = async (
        variantId: string,
        nextActive: boolean,
    ) => {
        try {
            await commerceApi.admin.updateVariantStatus(
                tenantSlug,
                variantId,
                nextActive,
            );
            toast.success(
                nextActive ? 'Variante activada.' : 'Variante pausada.',
            );
            await fetchProducts();
        } catch (err) {
            toast.error(
                err instanceof ApiClientError
                    ? err.message
                    : 'No se pudo cambiar el estado.',
            );
        }
    };

    return (
        <div>
            <Head title={`Catálogo · ${tenantName}`} />

            {/* Barra de herramientas y filtros compactos */}
            <div className="mb-6 space-y-3 rounded-xl border border-zinc-800 bg-[#121215] p-4">
                {/* Fila 1: Búsqueda y Botones de Acción */}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            setDebouncedSearch(searchDraft.trim());
                            setPage(1);
                        }}
                        className="flex flex-1 gap-2 sm:max-w-md"
                    >
                        <div className="relative flex-1">
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-zinc-500" />
                            <Input
                                value={searchDraft}
                                onChange={(e) => setSearchDraft(e.target.value)}
                                placeholder="Buscar producto o SKU..."
                                className="h-9 border-zinc-800 bg-zinc-950 pl-8 text-xs text-zinc-100"
                            />
                        </div>
                        {searchDraft && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() => {
                                    setSearchDraft('');
                                    setDebouncedSearch('');
                                    setPage(1);
                                }}
                                className="h-9 px-2 text-zinc-400 hover:text-zinc-200"
                                title="Limpiar búsqueda"
                            >
                                <X className="size-4" />
                            </Button>
                        )}
                        <Button
                            type="submit"
                            variant="secondary"
                            size="sm"
                            className="h-9 border border-zinc-700 bg-zinc-800 text-xs text-zinc-100"
                        >
                            Buscar
                        </Button>
                    </form>

                    <div className="flex items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => setCatModalOpen(true)}
                            className="h-9 border-zinc-700 bg-zinc-900 text-xs text-zinc-200"
                        >
                            <FolderPlus className="mr-1.5 size-3.5 text-indigo-400" />
                            Nueva Categoría ({flatCategories.length})
                        </Button>

                        <Button
                            type="button"
                            size="sm"
                            onClick={openCreateModal}
                            className="h-9 bg-indigo-500 text-xs font-semibold text-white hover:bg-indigo-400"
                        >
                            <Plus className="mr-1.5 size-3.5" />
                            Nuevo Producto
                        </Button>
                    </div>
                </div>

                {/* Fila 2: Filtros Compactos Rápidos */}
                <div className="flex flex-wrap items-center gap-2 border-t border-zinc-800/80 pt-3">
                    {/* Filtro Categoría */}
                    <select
                        value={selectedCategory}
                        onChange={(e) => {
                            setSelectedCategory(e.target.value);
                            setPage(1);
                        }}
                        className="h-8 rounded-lg border border-zinc-800 bg-zinc-950 px-2.5 text-xs text-zinc-300 focus:border-indigo-500 focus:outline-none"
                    >
                        <option value="">Todas las categorías</option>
                        {flatCategories.map((c) => (
                            <option key={c.id} value={c.id}>
                                {c.label}
                            </option>
                        ))}
                    </select>

                    {/* Filtro Estado */}
                    <select
                        value={selectedStatus}
                        onChange={(e) => {
                            setSelectedStatus(e.target.value);
                            setPage(1);
                        }}
                        className="h-8 rounded-lg border border-zinc-800 bg-zinc-950 px-2.5 text-xs text-zinc-300 focus:border-indigo-500 focus:outline-none"
                    >
                        <option value="">Todos los estados</option>
                        <option value="active">Activos</option>
                        <option value="inactive">Ocultos / Pausados</option>
                    </select>

                    {/* Filtro Stock */}
                    <select
                        value={selectedStock}
                        onChange={(e) => {
                            setSelectedStock(e.target.value);
                            setPage(1);
                        }}
                        className="h-8 rounded-lg border border-zinc-800 bg-zinc-950 px-2.5 text-xs text-zinc-300 focus:border-indigo-500 focus:outline-none"
                    >
                        <option value="">Todo el inventario</option>
                        <option value="in_stock">Con stock disponible</option>
                        <option value="out_of_stock">Agotados</option>
                    </select>

                    {/* Toggle Atributos Dinámicos */}
                    {schema.length > 0 && (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => setShowAttrFilters((prev) => !prev)}
                            className={`h-8 border-zinc-800 text-xs transition ${
                                showAttrFilters ||
                                Object.keys(selectedAttrs).length > 0
                                    ? 'border-indigo-500/50 bg-indigo-500/10 text-indigo-300'
                                    : 'bg-zinc-950 text-zinc-300 hover:bg-zinc-900'
                            }`}
                        >
                            <SlidersHorizontal className="mr-1.5 size-3.5" />
                            Atributos ({schema.length})
                            {Object.keys(selectedAttrs).length > 0 && (
                                <span className="ml-1.5 rounded-full bg-indigo-500/30 px-1.5 py-0.2 font-mono text-[10px] text-indigo-200">
                                    {Object.keys(selectedAttrs).length}
                                </span>
                            )}
                        </Button>
                    )}

                    {/* Botón limpiar */}
                    {hasActiveFilters && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={handleResetFilters}
                            className="h-8 text-xs text-zinc-400 hover:bg-zinc-800/60 hover:text-zinc-100"
                        >
                            <RotateCcw className="mr-1.5 size-3" />
                            Limpiar
                        </Button>
                    )}
                </div>

                {/* Fila 3: Tira expandible para Atributos Dinámicos con paginación de 4 en 4 (< >) */}
                {showAttrFilters && schema.length > 0 && (
                    <div className="rounded-lg border border-zinc-800/80 bg-zinc-950/60 p-3">
                        <div className="mb-2 flex items-center justify-between">
                            <span className="text-[11px] font-semibold uppercase tracking-wider text-zinc-400">
                                Filtros por Atributos ({schema.length})
                            </span>
                            {totalAttrPages > 1 && (
                                <div className="flex items-center gap-1.5">
                                    <span className="text-[10px] font-mono text-zinc-500">
                                        Pág. {attrPage} de {totalAttrPages}
                                    </span>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        disabled={attrPage <= 1}
                                        onClick={() =>
                                            setAttrPage((p) => Math.max(1, p - 1))
                                        }
                                        className="size-6 border-zinc-800 bg-zinc-900 p-0 text-zinc-300 hover:bg-zinc-800 hover:text-white disabled:opacity-40"
                                        title="Anterior"
                                    >
                                        <ChevronLeft className="size-3" />
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        disabled={attrPage >= totalAttrPages}
                                        onClick={() =>
                                            setAttrPage((p) =>
                                                Math.min(totalAttrPages, p + 1),
                                            )
                                        }
                                        className="size-6 border-zinc-800 bg-zinc-900 p-0 text-zinc-300 hover:bg-zinc-800 hover:text-white disabled:opacity-40"
                                        title="Siguiente"
                                    >
                                        <ChevronRight className="size-3" />
                                    </Button>
                                </div>
                            )}
                        </div>

                        <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                            {visibleAttrs.map((attrDef) => (
                                <div
                                    key={attrDef.key}
                                    className="flex flex-col gap-1"
                                >
                                    <label className="truncate text-[11px] font-medium text-zinc-400">
                                        {attrDef.label}
                                    </label>
                                    {attrDef.options &&
                                    attrDef.options.length > 0 ? (
                                        <select
                                            value={
                                                selectedAttrs[attrDef.key] ?? ''
                                            }
                                            onChange={(e) => {
                                                const val = e.target.value;
                                                setSelectedAttrs((prev) => {
                                                    const next = { ...prev };
                                                    if (val) {
                                                        next[attrDef.key] = val;
                                                    } else {
                                                        delete next[
                                                            attrDef.key
                                                        ];
                                                    }
                                                    return next;
                                                });
                                                setPage(1);
                                            }}
                                            className="h-8 rounded-lg border border-zinc-800 bg-zinc-900 px-2 text-xs text-zinc-200 focus:border-indigo-500 focus:outline-none"
                                        >
                                            <option value="">Todos</option>
                                            {attrDef.options.map((opt) => (
                                                <option key={opt} value={opt}>
                                                    {opt}
                                                </option>
                                            ))}
                                        </select>
                                    ) : attrDef.data_type === 'boolean' ? (
                                        <select
                                            value={
                                                selectedAttrs[attrDef.key] ?? ''
                                            }
                                            onChange={(e) => {
                                                const val = e.target.value;
                                                setSelectedAttrs((prev) => {
                                                    const next = { ...prev };
                                                    if (val) {
                                                        next[attrDef.key] = val;
                                                    } else {
                                                        delete next[
                                                            attrDef.key
                                                        ];
                                                    }
                                                    return next;
                                                });
                                                setPage(1);
                                            }}
                                            className="h-8 rounded-lg border border-zinc-800 bg-zinc-900 px-2 text-xs text-zinc-200 focus:border-indigo-500 focus:outline-none"
                                        >
                                            <option value="">Todos</option>
                                            <option value="true">Sí</option>
                                            <option value="false">No</option>
                                        </select>
                                    ) : (
                                        <Input
                                            value={
                                                selectedAttrs[attrDef.key] ?? ''
                                            }
                                            onChange={(e) => {
                                                const val = e.target.value;
                                                setSelectedAttrs((prev) => {
                                                    const next = { ...prev };
                                                    if (val.trim()) {
                                                        next[attrDef.key] =
                                                            val.trim();
                                                    } else {
                                                        delete next[
                                                            attrDef.key
                                                        ];
                                                    }
                                                    return next;
                                                });
                                                setPage(1);
                                            }}
                                            placeholder={`Filtrar ${attrDef.label.toLowerCase()}...`}
                                            className="h-8 border-zinc-800 bg-zinc-900 text-xs text-zinc-200"
                                        />
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* Fila 4: Chips de filtros activos */}
                {hasActiveFilters && (
                    <div className="flex flex-wrap items-center gap-1.5 pt-1">
                        <span className="text-[11px] text-zinc-500">
                            Filtros activos:
                        </span>
                        {debouncedSearch && (
                            <span className="inline-flex items-center gap-1 rounded-md border border-zinc-800 bg-zinc-900 px-2 py-0.5 text-[11px] text-zinc-300">
                                Búsqueda: &quot;{debouncedSearch}&quot;
                                <button
                                    type="button"
                                    onClick={() => {
                                        setSearchDraft('');
                                        setDebouncedSearch('');
                                        setPage(1);
                                    }}
                                    className="ml-0.5 text-zinc-500 hover:text-zinc-200"
                                >
                                    <X className="size-3" />
                                </button>
                            </span>
                        )}
                        {selectedCategory && (
                            <span className="inline-flex items-center gap-1 rounded-md border border-zinc-800 bg-zinc-900 px-2 py-0.5 text-[11px] text-zinc-300">
                                Cat:{' '}
                                {flatCategories.find(
                                    (c) => c.id === selectedCategory,
                                )?.label ?? selectedCategory}
                                <button
                                    type="button"
                                    onClick={() => {
                                        setSelectedCategory('');
                                        setPage(1);
                                    }}
                                    className="ml-0.5 text-zinc-500 hover:text-zinc-200"
                                >
                                    <X className="size-3" />
                                </button>
                            </span>
                        )}
                        {selectedStatus && (
                            <span className="inline-flex items-center gap-1 rounded-md border border-zinc-800 bg-zinc-900 px-2 py-0.5 text-[11px] text-zinc-300">
                                Estado:{' '}
                                {selectedStatus === 'active'
                                    ? 'Activos'
                                    : 'Ocultos'}
                                <button
                                    type="button"
                                    onClick={() => {
                                        setSelectedStatus('');
                                        setPage(1);
                                    }}
                                    className="ml-0.5 text-zinc-500 hover:text-zinc-200"
                                >
                                    <X className="size-3" />
                                </button>
                            </span>
                        )}
                        {selectedStock && (
                            <span className="inline-flex items-center gap-1 rounded-md border border-zinc-800 bg-zinc-900 px-2 py-0.5 text-[11px] text-zinc-300">
                                Stock:{' '}
                                {selectedStock === 'in_stock'
                                    ? 'Con stock'
                                    : 'Agotados'}
                                <button
                                    type="button"
                                    onClick={() => {
                                        setSelectedStock('');
                                        setPage(1);
                                    }}
                                    className="ml-0.5 text-zinc-500 hover:text-zinc-200"
                                >
                                    <X className="size-3" />
                                </button>
                            </span>
                        )}
                        {Object.entries(selectedAttrs).map(([k, v]) => (
                            <span
                                key={k}
                                className="inline-flex items-center gap-1 rounded-md border border-zinc-800 bg-zinc-900 px-2 py-0.5 text-[11px] text-zinc-300"
                            >
                                {schema.find((s) => s.key === k)?.label ?? k}:{' '}
                                {v}
                                <button
                                    type="button"
                                    onClick={() => {
                                        setSelectedAttrs((prev) => {
                                            const next = { ...prev };
                                            delete next[k];
                                            return next;
                                        });
                                        setPage(1);
                                    }}
                                    className="ml-0.5 text-zinc-500 hover:text-zinc-200"
                                >
                                    <X className="size-3" />
                                </button>
                            </span>
                        ))}
                    </div>
                )}
            </div>

            {/* Listado de Productos y Matriz de Stock por Variante */}
            {isLoading ? (
                <div className="flex items-center justify-center rounded-xl border border-zinc-800 bg-[#121215] py-16 text-xs text-zinc-400">
                    <Loader2 className="mr-2 size-4 animate-spin text-primary" />
                    Cargando catálogo y esquema dinámico...
                </div>
            ) : products.length === 0 ? (
                <div className="rounded-xl border border-dashed border-zinc-800 bg-[#121215]/60 p-12 text-center">
                    <Package className="mx-auto size-10 text-zinc-600" />
                    <p className="mt-3 text-sm font-medium text-zinc-200">
                        {hasActiveFilters
                            ? 'No se encontraron productos con los filtros aplicados'
                            : 'Sin productos registrados'}
                    </p>
                    <p className="mt-1 text-xs text-zinc-400">
                        {hasActiveFilters
                            ? 'Intenta cambiar el término de búsqueda o restablecer los filtros.'
                            : 'Crea tu primer producto adaptado automáticamente al metadata_schema de la tienda.'}
                    </p>
                    {hasActiveFilters && (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={handleResetFilters}
                            className="mt-4 border-zinc-700 bg-zinc-900 text-xs text-zinc-200 hover:bg-zinc-800"
                        >
                            <RotateCcw className="mr-1.5 size-3.5" />
                            Limpiar todos los filtros
                        </Button>
                    )}
                </div>
            ) : (
                <div className="space-y-4">
                    {products.map((product) => (
                        <div
                            key={product.id}
                            className="rounded-xl border border-zinc-800 bg-[#121215] p-4"
                        >
                            <div className="flex flex-wrap items-start justify-between gap-3 border-b border-zinc-800/80 pb-3">
                                <div className="flex items-center gap-3">
                                    <div className="size-12 shrink-0 overflow-hidden rounded-lg border border-zinc-800 bg-zinc-900">
                                        {product.images[0] ? (
                                            <img
                                                src={product.images[0]}
                                                alt={product.name}
                                                className="size-full object-cover"
                                            />
                                        ) : (
                                            <div className="flex size-full items-center justify-center text-zinc-600">
                                                <Package className="size-5" />
                                            </div>
                                        )}
                                    </div>
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <h2 className="text-sm font-semibold text-zinc-100">
                                                {product.name}
                                            </h2>
                                            <span
                                                className={`rounded-full border px-2 py-0.5 text-[10px] ${
                                                    product.is_active
                                                        ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300'
                                                        : 'border-zinc-700 bg-zinc-800 text-zinc-400'
                                                }`}
                                            >
                                                {product.is_active
                                                    ? 'Activo'
                                                    : 'Oculto'}
                                            </span>
                                        </div>
                                        <p className="mt-0.5 font-mono text-xs text-zinc-400">
                                            Base:{' '}
                                            {formatMoney(
                                                product.base_price,
                                                currencyCode,
                                            )}{' '}
                                            · Stock Total: {product.total_stock}{' '}
                                            uds.
                                        </p>
                                    </div>
                                </div>

                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => openEditModal(product)}
                                    className="h-8 border-zinc-800 bg-zinc-950 text-xs text-zinc-200"
                                >
                                    <Edit3 className="mr-1.5 size-3.5 text-indigo-400" />
                                    Editar
                                </Button>
                            </div>

                            {/* Variantes con Ajuste Atómico de Stock */}
                            <div className="mt-3 grid grid-cols-1 gap-2 md:grid-cols-2 xl:grid-cols-3">
                                {product.variants.map((v) => (
                                    <div
                                        key={v.id}
                                        className="flex items-center justify-between gap-2 rounded-lg border border-zinc-800/80 bg-zinc-950/70 px-3 py-2 text-xs"
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate font-mono font-semibold text-zinc-200">
                                                {v.sku}
                                            </p>
                                            <p className="truncate text-[11px] text-zinc-500">
                                                {formatVariantAttributes(
                                                    v.metadata,
                                                ) || 'Estándar'}{' '}
                                                ·{' '}
                                                {formatMoney(
                                                    v.price,
                                                    currencyCode,
                                                )}
                                            </p>
                                        </div>

                                        <div className="flex shrink-0 items-center gap-1.5">
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    void handleQuickStockDelta(
                                                        v.id,
                                                        -1,
                                                    )
                                                }
                                                disabled={v.stock <= 0}
                                                className="rounded border border-zinc-800 bg-zinc-900 p-1 text-zinc-400 hover:text-zinc-100 disabled:opacity-40"
                                                title="Restar 1 unidad"
                                            >
                                                <Minus className="size-3" />
                                            </button>
                                            <span className="min-w-8 text-center font-mono text-xs font-bold text-emerald-400">
                                                {v.stock}
                                            </span>
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    void handleQuickStockDelta(
                                                        v.id,
                                                        1,
                                                    )
                                                }
                                                className="rounded border border-zinc-800 bg-zinc-900 p-1 text-zinc-400 hover:text-zinc-100"
                                                title="Sumar 1 unidad"
                                            >
                                                <Plus className="size-3" />
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    void handleToggleVariantStatus(
                                                        v.id,
                                                        !v.is_active,
                                                    )
                                                }
                                                className={`ml-1 rounded px-1.5 py-0.5 font-mono text-[10px] ${
                                                    v.is_active
                                                        ? 'bg-emerald-500/15 text-emerald-300'
                                                        : 'bg-zinc-800 text-zinc-500'
                                                }`}
                                            >
                                                {v.is_active ? 'ON' : 'OFF'}
                                            </button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {/* Paginación Inferior sin números (< >) */}
            {meta && meta.total > 0 && (
                <div className="mt-6 flex flex-col gap-3 rounded-xl border border-zinc-800 bg-[#121215] px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-2 text-xs text-zinc-400">
                        <span>
                            Mostrando{' '}
                            <strong className="text-zinc-200">
                                {(meta.current_page - 1) * meta.per_page + 1}
                            </strong>{' '}
                            a{' '}
                            <strong className="text-zinc-200">
                                {Math.min(
                                    meta.current_page * meta.per_page,
                                    meta.total,
                                )}
                            </strong>{' '}
                            de{' '}
                            <strong className="text-zinc-200">
                                {meta.total}
                            </strong>{' '}
                            productos
                        </span>
                        <span className="text-zinc-700">|</span>
                        <div className="flex items-center gap-1.5">
                            <span className="text-zinc-500">Por página:</span>
                            <select
                                value={perPage}
                                onChange={(e) => {
                                    setPerPage(Number(e.target.value));
                                    setPage(1);
                                }}
                                className="h-7 rounded border border-zinc-800 bg-zinc-950 px-2 text-xs text-zinc-200 focus:border-indigo-500 focus:outline-none"
                            >
                                <option value={10}>10</option>
                                <option value={20}>20</option>
                                <option value={50}>50</option>
                            </select>
                        </div>
                    </div>

                    <div className="flex items-center gap-2 self-end sm:self-auto">
                        <span className="text-xs text-zinc-400">
                            Página{' '}
                            <strong className="text-zinc-200">
                                {meta.current_page}
                            </strong>{' '}
                            de{' '}
                            <strong className="text-zinc-200">
                                {meta.last_page}
                            </strong>
                        </span>
                        <div className="flex items-center gap-1">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={meta.current_page <= 1 || isLoading}
                                onClick={() =>
                                    setPage((prev) => Math.max(1, prev - 1))
                                }
                                className="size-7 border-zinc-800 bg-zinc-900 p-0 text-zinc-300 hover:bg-zinc-800 hover:text-white disabled:opacity-40"
                                title="Página anterior"
                            >
                                <ChevronLeft className="size-4" />
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={
                                    meta.current_page >= meta.last_page ||
                                    isLoading
                                }
                                onClick={() =>
                                    setPage((prev) =>
                                        Math.min(meta.last_page, prev + 1),
                                    )
                                }
                                className="size-7 border-zinc-800 bg-zinc-900 p-0 text-zinc-300 hover:bg-zinc-800 hover:text-white disabled:opacity-40"
                                title="Página siguiente"
                            >
                                <ChevronRight className="size-4" />
                            </Button>
                        </div>
                    </div>
                </div>
            )}

            {/* Modal Nueva Categoría */}
            <Dialog open={catModalOpen} onOpenChange={setCatModalOpen}>
                <DialogContent className="border-zinc-800 bg-[#121215] text-zinc-100 sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Nueva Categoría</DialogTitle>
                        <DialogDescription className="text-xs text-zinc-400">
                            Organiza el árbol de navegación de la vitrina
                            pública.
                        </DialogDescription>
                    </DialogHeader>
                    <form
                        onSubmit={(e) => void handleSaveCategory(e)}
                        className="space-y-4"
                    >
                        <div>
                            <Label className="text-xs text-zinc-300">
                                Nombre de Categoría *
                            </Label>
                            <Input
                                required
                                value={newCatName}
                                onChange={(e) => setNewCatName(e.target.value)}
                                placeholder="Ej. Calzado Técnico"
                                className="mt-1 border-zinc-800 bg-zinc-950 text-xs"
                            />
                        </div>
                        <div>
                            <Label className="text-xs text-zinc-300">
                                Categoría Padre (Opcional)
                            </Label>
                            <select
                                value={newCatParentId}
                                onChange={(e) =>
                                    setNewCatParentId(e.target.value)
                                }
                                className="mt-1 h-9 w-full rounded-md border border-zinc-800 bg-zinc-950 px-3 text-xs text-zinc-100"
                            >
                                <option value="">Raíz principal</option>
                                {categories.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <Button
                            type="submit"
                            disabled={isSavingCat}
                            className="w-full bg-indigo-500 text-xs font-semibold text-white"
                        >
                            {isSavingCat ? 'Guardando...' : 'Crear Categoría'}
                        </Button>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Modal Crear / Editar Producto Schema-Driven */}
            <Dialog open={productModalOpen} onOpenChange={setProductModalOpen}>
                <DialogContent className="max-h-[90vh] overflow-y-auto border-zinc-800 bg-[#121215] text-zinc-100 sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>
                            {editingId
                                ? 'Editar Producto y Variantes'
                                : 'Nuevo Producto Schema-Driven'}
                        </DialogTitle>
                        <DialogDescription className="text-xs text-zinc-400">
                            Los campos adicionales se generan automáticamente
                            según tu{' '}
                            <code className="font-mono">metadata_schema</code>.
                        </DialogDescription>
                    </DialogHeader>

                    <form
                        onSubmit={(e) => void handleSaveProduct(e)}
                        className="space-y-5"
                    >
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div className="sm:col-span-2">
                                <Label className="text-xs text-zinc-300">
                                    Nombre del Producto *
                                </Label>
                                <Input
                                    required
                                    value={prodName}
                                    onChange={(e) =>
                                        setProdName(e.target.value)
                                    }
                                    placeholder="Ej. Chaqueta Impermeable Kinetic Pro"
                                    className="mt-1 border-zinc-800 bg-zinc-950 text-xs"
                                />
                            </div>

                            <div>
                                <Label className="text-xs text-zinc-300">
                                    Categoría
                                </Label>
                                <select
                                    value={prodCategoryId}
                                    onChange={(e) =>
                                        setProdCategoryId(e.target.value)
                                    }
                                    className="mt-1 h-9 w-full rounded-md border border-zinc-800 bg-zinc-950 px-3 text-xs text-zinc-100"
                                >
                                    <option value="">Sin categoría</option>
                                    {flatCategories.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.label}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <Label className="text-xs text-zinc-300">
                                    Precio Base ({currencyCode}) *
                                </Label>
                                <Input
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    required
                                    value={prodBasePrice}
                                    onChange={(e) =>
                                        setProdBasePrice(e.target.value)
                                    }
                                    className="mt-1 border-zinc-800 bg-zinc-950 font-mono text-xs"
                                />
                            </div>

                            <div className="sm:col-span-2">
                                <Label className="text-xs text-zinc-300">
                                    Descripción Comercial
                                </Label>
                                <Input
                                    value={prodDescription}
                                    onChange={(e) =>
                                        setProdDescription(e.target.value)
                                    }
                                    placeholder="Resumen de materiales, beneficios o garantía..."
                                    className="mt-1 border-zinc-800 bg-zinc-950 text-xs"
                                />
                            </div>

                            <div className="sm:col-span-2">
                                <Label className="text-xs text-zinc-300">
                                    URLs de Imágenes (separadas por coma)
                                </Label>
                                <Input
                                    value={prodImagesCsv}
                                    onChange={(e) =>
                                        setProdImagesCsv(e.target.value)
                                    }
                                    placeholder="https://images.unsplash.com/..."
                                    className="mt-1 border-zinc-800 bg-zinc-950 font-mono text-xs"
                                />
                            </div>
                        </div>

                        {/* Atributos de Producto (affects_stock === false) */}
                        {schema.some((a) => !a.affects_stock) && (
                            <div className="rounded-xl border border-zinc-800 bg-zinc-950/60 p-3.5">
                                <p className="mb-2.5 text-[11px] font-semibold tracking-wider text-indigo-300 uppercase">
                                    Atributos Generales del Producto (
                                    affects_stock: false)
                                </p>
                                <DynamicMetadataFields
                                    schema={schema}
                                    affectsStock={false}
                                    value={prodMetadata}
                                    onChange={setProdMetadata}
                                />
                            </div>
                        )}

                        {/* Variantes + Atributos de Variante (affects_stock === true) */}
                        <div className="space-y-3">
                            <div className="flex items-center justify-between">
                                <p className="text-xs font-semibold text-zinc-200">
                                    Variantes e Inventario (
                                    {variantsDraft.length})
                                </p>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        setVariantsDraft((prev) => [
                                            ...prev,
                                            emptyVariantDraft(),
                                        ])
                                    }
                                    className="h-7 border-zinc-700 bg-zinc-900 text-xs"
                                >
                                    <Plus className="mr-1 size-3" />
                                    Agregar Variante
                                </Button>
                            </div>

                            {variantsDraft.map((variant, idx) => (
                                <div
                                    key={variant.id ?? `draft-${idx}`}
                                    className="space-y-3 rounded-xl border border-zinc-800 bg-zinc-950/70 p-3.5"
                                >
                                    <div className="flex items-center justify-between">
                                        <span className="font-mono text-xs font-semibold text-zinc-300">
                                            Variante #{idx + 1}
                                        </span>
                                        {variantsDraft.length > 1 && (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setVariantsDraft((prev) =>
                                                        prev.filter(
                                                            (_, i) => i !== idx,
                                                        ),
                                                    )
                                                }
                                                className="text-zinc-500 hover:text-rose-400"
                                            >
                                                <Trash2 className="size-3.5" />
                                            </button>
                                        )}
                                    </div>

                                    <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-3">
                                        <div>
                                            <Label className="text-[11px] text-zinc-400">
                                                SKU (opcional)
                                            </Label>
                                            <Input
                                                value={variant.sku ?? ''}
                                                onChange={(e) => {
                                                    const copy = [
                                                        ...variantsDraft,
                                                    ];
                                                    copy[idx] = {
                                                        ...variant,
                                                        sku: e.target.value,
                                                    };
                                                    setVariantsDraft(copy);
                                                }}
                                                placeholder="Auto-generado"
                                                className="mt-1 h-8 border-zinc-800 bg-zinc-900 font-mono text-xs"
                                            />
                                        </div>

                                        <div>
                                            <Label className="text-[11px] text-zinc-400">
                                                Precio Override (opcional)
                                            </Label>
                                            <Input
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                value={variant.price ?? ''}
                                                onChange={(e) => {
                                                    const copy = [
                                                        ...variantsDraft,
                                                    ];
                                                    copy[idx] = {
                                                        ...variant,
                                                        price:
                                                            e.target.value ===
                                                            ''
                                                                ? null
                                                                : Number(
                                                                      e.target
                                                                          .value,
                                                                  ),
                                                    };
                                                    setVariantsDraft(copy);
                                                }}
                                                placeholder="Usa precio base"
                                                className="mt-1 h-8 border-zinc-800 bg-zinc-900 font-mono text-xs"
                                            />
                                        </div>

                                        <div>
                                            <Label className="text-[11px] text-zinc-400">
                                                Stock Disponible *
                                            </Label>
                                            <Input
                                                type="number"
                                                min="0"
                                                required
                                                value={variant.stock}
                                                onChange={(e) => {
                                                    const copy = [
                                                        ...variantsDraft,
                                                    ];
                                                    copy[idx] = {
                                                        ...variant,
                                                        stock: Number(
                                                            e.target.value,
                                                        ),
                                                    };
                                                    setVariantsDraft(copy);
                                                }}
                                                className="mt-1 h-8 border-zinc-800 bg-zinc-900 font-mono text-xs"
                                            />
                                        </div>
                                    </div>

                                    {schema.some((a) => a.affects_stock) && (
                                        <DynamicMetadataFields
                                            schema={schema}
                                            affectsStock
                                            compact
                                            value={variant.metadata ?? {}}
                                            onChange={(nextMeta) => {
                                                const copy = [...variantsDraft];
                                                copy[idx] = {
                                                    ...variant,
                                                    metadata: nextMeta,
                                                };
                                                setVariantsDraft(copy);
                                            }}
                                        />
                                    )}
                                </div>
                            ))}
                        </div>

                        <div className="flex items-center justify-between border-t border-zinc-800 pt-4">
                            <label className="flex items-center gap-2 text-xs text-zinc-300">
                                <input
                                    type="checkbox"
                                    checked={prodIsActive}
                                    onChange={(e) =>
                                        setProdIsActive(e.target.checked)
                                    }
                                    className="rounded border-zinc-700 bg-zinc-900"
                                />
                                Visible en la vitrina pública
                            </label>

                            <Button
                                type="submit"
                                disabled={isSavingProduct}
                                className="bg-indigo-500 text-xs font-semibold text-white hover:bg-indigo-400"
                            >
                                {isSavingProduct
                                    ? 'Guardando...'
                                    : editingId
                                      ? 'Actualizar Producto'
                                      : 'Publicar Producto'}
                            </Button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    );
}

export default function TenantAdminCatalogPage(props: PortalShellPageProps) {
    return (
        <TenantAdminLayout
            tenantSlug={props.tenantSlug}
            tenantName={props.tenantName}
            currencyCode={props.currencyCode}
            countryCode={props.countryCode}
            urlMode={props.urlMode}
            activeSection="catalog"
            title="Catálogo y Variantes"
            subtitle="Productos dinámicos gobernados por metadata_schema y control de stock en tiempo real"
        >
            <CatalogAdminContent />
        </TenantAdminLayout>
    );
}
