import { Head } from '@inertiajs/react';
import {
    Edit3,
    FolderPlus,
    Loader2,
    Minus,
    Package,
    Plus,
    Search,
    Trash2,
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
    const [categories, setCategories] = useState<CategoryNode[]>([]);
    const [schema, setSchema] = useState<MetadataAttributeDefinition[]>([]);
    const [searchDraft, setSearchDraft] = useState<string>('');
    const [isLoading, setIsLoading] = useState<boolean>(true);

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

    const loadAll = useCallback(
        async (q = '') => {
            setIsLoading(true);
            try {
                const [prodRes, catRes, configRes] = await Promise.all([
                    commerceApi.admin.getProducts(tenantSlug, {
                        q: q || undefined,
                        per_page: 30,
                    }),
                    commerceApi.admin.getCategories(tenantSlug),
                    commerceApi.admin.getConfig(tenantSlug),
                ]);
                setProducts(prodRes.data);
                setCategories(catRes.data);
                setSchema(configRes.metadata_schema ?? []);
            } catch (err) {
                toast.error(
                    err instanceof ApiClientError
                        ? err.message
                        : 'Error al cargar el catálogo administrativo.',
                );
            } finally {
                setIsLoading(false);
            }
        },
        [tenantSlug],
    );

    useEffect(() => {
        void loadAll();
    }, [loadAll]);

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
            await loadAll(searchDraft.trim());
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
            await loadAll(searchDraft.trim());
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
            await loadAll(searchDraft.trim());
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
            await loadAll(searchDraft.trim());
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

            {/* Barra de herramientas */}
            <div className="mb-6 flex flex-col gap-3 rounded-xl border border-zinc-800 bg-[#121215] p-4 sm:flex-row sm:items-center sm:justify-between">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        void loadAll(searchDraft.trim());
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
                        Sin productos registrados
                    </p>
                    <p className="mt-1 text-xs text-zinc-400">
                        Crea tu primer producto adaptado automáticamente al{' '}
                        <code className="font-mono">metadata_schema</code> de la
                        tienda.
                    </p>
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
