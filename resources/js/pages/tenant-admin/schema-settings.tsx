import { Head } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    Gift,
    Loader2,
    Palette,
    Phone,
    Plus,
    Save,
    Sliders,
    Trash2,
    Truck,
} from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTenant } from '@/contexts/tenant-context';
import { TenantAdminLayout } from '@/layouts/tenant-admin-layout';
import { ApiClientError, commerceApi } from '@/lib/api-client';
import {
    type BankAccountInfo,
    type FilterConfigDefinition,
    type MetadataAttributeDefinition,
    type MetadataDataType,
    type PartnerStoreAdmin,
    type PortalShellPageProps,
    RESERVED_METADATA_KEYWORDS,
    type ShippingZone,
} from '@/types/commerce';

type ConfigTab = 'schema' | 'branding' | 'shipping' | 'contact' | 'rewards';

function toSnakeKey(text: string): string {
    return text
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '')
        .replace(/^([0-9])/, 'attr_$1');
}

function generateUniqueKey(
    label: string,
    existingKeys: string[],
    currentKey?: string,
): string {
    let baseKey = toSnakeKey(label);
    if (!baseKey) {
        baseKey = 'atributo';
    }
    if ((RESERVED_METADATA_KEYWORDS as readonly string[]).includes(baseKey)) {
        baseKey = `custom_${baseKey}`;
    }
    let candidate = baseKey;
    let counter = 1;
    while (existingKeys.includes(candidate) && candidate !== currentKey) {
        candidate = `${baseKey}_${counter}`;
        counter++;
    }
    return candidate;
}

function SchemaSettingsContent() {
    const { tenantSlug, tenantName, currencyCode, refreshBootstrap } =
        useTenant();

    const [activeTab, setActiveTab] = useState<ConfigTab>('schema');
    const [isLoading, setIsLoading] = useState<boolean>(true);
    const [isSaving, setIsSaving] = useState<boolean>(false);

    // 1. Catalog Schema + Filter Config
    const [metadataSchema, setMetadataSchema] = useState<
        MetadataAttributeDefinition[]
    >([]);
    const [enabledFacetKeys, setEnabledFacetKeys] = useState<string[]>([]);
    const [enablePriceFilter, setEnablePriceFilter] = useState<boolean>(true);

    const SCHEMA_PAGE_SIZE = 4;
    const [schemaPage, setSchemaPage] = useState<number>(1);
    const totalSchemaPages = Math.max(
        1,
        Math.ceil(metadataSchema.length / SCHEMA_PAGE_SIZE),
    );

    useEffect(() => {
        if (schemaPage > totalSchemaPages) {
            setSchemaPage(totalSchemaPages);
        }
    }, [metadataSchema.length, schemaPage, totalSchemaPages]);

    const startSchemaIdx = (schemaPage - 1) * SCHEMA_PAGE_SIZE;
    const visibleSchemaAttrs = metadataSchema.slice(
        startSchemaIdx,
        startSchemaIdx + SCHEMA_PAGE_SIZE,
    );

    // 2. Branding
    const [primaryColor, setPrimaryColor] = useState<string>('#6366f1');
    const [secondaryColor, setSecondaryColor] = useState<string>('#18181b');
    const [accentColor, setAccentColor] = useState<string>('#10b981');
    const [fontFamily, setFontFamily] = useState<string>('Instrument Sans');
    const [logoUrl, setLogoUrl] = useState<string>('');
    const [bannerUrl, setBannerUrl] = useState<string>('');

    // 3. Shipping Zones
    const [shippingZones, setShippingZones] = useState<ShippingZone[]>([]);

    // 4. Contact Info & Bank Accounts
    const [whatsappSales, setWhatsappSales] = useState<string>('');
    const [supportEmail, setSupportEmail] = useState<string>('');
    const [bankAccounts, setBankAccounts] = useState<BankAccountInfo[]>([]);

    // 5. Outbound Rewards
    const [partners, setPartners] = useState<PartnerStoreAdmin[]>([]);
    const [rewardsEnabled, setRewardsEnabled] = useState<boolean>(false);
    const [rewardPartnerId, setRewardPartnerId] = useState<string>('');
    const [rewardMinAmount, setRewardMinAmount] = useState<string>('500');
    const [rewardValidityDays, setRewardValidityDays] = useState<string>('30');

    const loadConfig = useCallback(async () => {
        setIsLoading(true);
        try {
            const [cfg, partnerRes] = await Promise.all([
                commerceApi.admin.getConfig(tenantSlug),
                commerceApi.admin.getPartnerStores(tenantSlug),
            ]);

            setMetadataSchema(cfg.metadata_schema ?? []);
            const filters = cfg.filter_config ?? [];
            setEnablePriceFilter(
                filters.length === 0 ||
                    filters.some(
                        (f) => f.type === 'system' && f.key === 'price',
                    ),
            );
            setEnabledFacetKeys(
                filters.filter((f) => f.type === 'metadata').map((f) => f.key),
            );

            setPrimaryColor(cfg.branding?.primary_color ?? '#6366f1');
            setSecondaryColor(cfg.branding?.secondary_color ?? '#18181b');
            setAccentColor(cfg.branding?.accent_color ?? '#10b981');
            setFontFamily(cfg.branding?.font_family ?? 'Instrument Sans');
            setLogoUrl(cfg.branding?.logo_url ?? '');
            setBannerUrl(cfg.branding?.banner_url ?? '');

            setShippingZones(cfg.shipping_zones ?? []);
            setWhatsappSales(cfg.contact_info?.whatsapp_sales ?? '');
            setSupportEmail(cfg.contact_info?.support_email ?? '');
            setBankAccounts(cfg.contact_info?.bank_accounts ?? []);

            setPartners(partnerRes.data);
            setRewardsEnabled(Boolean(cfg.outbound_reward_rules?.is_enabled));
            setRewardPartnerId(
                cfg.outbound_reward_rules?.partner_store_id ??
                    partnerRes.data[0]?.id ??
                    '',
            );
            setRewardMinAmount(
                String(cfg.outbound_reward_rules?.min_order_amount ?? 500),
            );
            setRewardValidityDays(
                String(cfg.outbound_reward_rules?.validity_days ?? 30),
            );
        } catch (err) {
            toast.error(
                err instanceof ApiClientError
                    ? err.message
                    : 'Error al cargar la configuración del tenant.',
            );
        } finally {
            setIsLoading(false);
        }
    }, [tenantSlug]);

    useEffect(() => {
        void loadConfig();
    }, [loadConfig]);

    const handleSaveSchema = async () => {
        for (const attr of metadataSchema) {
            if (!/^[a-z][a-z0-9_]*$/.test(attr.key)) {
                toast.error(
                    `La llave "${attr.key}" debe iniciar con letra minúscula y usar solo snake_case.`,
                );
                return;
            }
            if (
                (RESERVED_METADATA_KEYWORDS as readonly string[]).includes(
                    attr.key,
                )
            ) {
                toast.error(
                    `La llave "${attr.key}" es una palabra reservada del sistema.`,
                );
                return;
            }
        }

        setIsSaving(true);
        try {
            const filterConfig: FilterConfigDefinition[] = [];
            let order = 0;
            if (enablePriceFilter) {
                filterConfig.push({
                    type: 'system',
                    key: 'price',
                    label: 'Rango de Precio',
                    data_type: 'range',
                    order: order++,
                });
            }
            for (const attr of metadataSchema) {
                if (enabledFacetKeys.includes(attr.key)) {
                    filterConfig.push({
                        type: 'metadata',
                        key: attr.key,
                        label: attr.label,
                        data_type: attr.data_type,
                        order: order++,
                    });
                }
            }

            await commerceApi.admin.updateCatalogSchema(tenantSlug, {
                metadata_schema: metadataSchema,
                filter_config: filterConfig,
            });
            await refreshBootstrap();
            toast.success('Esquema de catálogo y facetas dinámicas guardados.');
        } catch (err) {
            toast.error(
                err instanceof ApiClientError
                    ? (err.firstFieldError() ?? err.message)
                    : 'Error al guardar el esquema.',
            );
        } finally {
            setIsSaving(false);
        }
    };

    const handleSaveBranding = async () => {
        setIsSaving(true);
        try {
            await commerceApi.admin.updateBranding(tenantSlug, {
                primary_color: primaryColor,
                secondary_color: secondaryColor,
                accent_color: accentColor,
                font_family: fontFamily.trim() || null,
                logo_url: logoUrl.trim() || null,
                banner_url: bannerUrl.trim() || null,
            });
            await refreshBootstrap();
            toast.success('Identidad visual aplicada al instante.');
        } catch (err) {
            toast.error(
                err instanceof ApiClientError
                    ? (err.firstFieldError() ?? err.message)
                    : 'Error al guardar branding.',
            );
        } finally {
            setIsSaving(false);
        }
    };

    const handleSaveShipping = async () => {
        setIsSaving(true);
        try {
            await commerceApi.admin.updateShippingZones(
                tenantSlug,
                shippingZones,
            );
            await refreshBootstrap();
            toast.success('Zonas de envío actualizadas.');
        } catch (err) {
            toast.error(
                err instanceof ApiClientError
                    ? (err.firstFieldError() ?? err.message)
                    : 'Error al actualizar zonas de envío.',
            );
        } finally {
            setIsSaving(false);
        }
    };

    const handleSaveContact = async () => {
        setIsSaving(true);
        try {
            await commerceApi.admin.updateContactInfo(tenantSlug, {
                whatsapp_sales: whatsappSales.trim() || null,
                support_email: supportEmail.trim() || null,
                bank_accounts: bankAccounts,
            });
            toast.success('Contacto WhatsApp y cuentas bancarias guardados.');
        } catch (err) {
            toast.error(
                err instanceof ApiClientError
                    ? (err.firstFieldError() ?? err.message)
                    : 'Error al guardar información de contacto.',
            );
        } finally {
            setIsSaving(false);
        }
    };

    const handleSaveRewards = async () => {
        setIsSaving(true);
        try {
            await commerceApi.admin.updateOutboundRewards(tenantSlug, {
                is_enabled: rewardsEnabled,
                partner_store_id: rewardsEnabled ? rewardPartnerId : null,
                min_order_amount: rewardsEnabled
                    ? Number(rewardMinAmount)
                    : null,
                validity_days: rewardsEnabled
                    ? Number(rewardValidityDays)
                    : null,
            });
            toast.success('Reglas de recompensas cruzadas actualizadas.');
        } catch (err) {
            toast.error(
                err instanceof ApiClientError
                    ? (err.firstFieldError() ?? err.message)
                    : 'Error al guardar reglas de recompensas.',
            );
        } finally {
            setIsSaving(false);
        }
    };

    if (isLoading) {
        return (
            <div className="flex items-center justify-center rounded-xl border border-zinc-800 bg-[#121215] py-20 text-xs text-zinc-400">
                <Loader2 className="mr-2 size-4 animate-spin text-primary" />
                Cargando motor de configuración...
            </div>
        );
    }

    return (
        <div className="space-y-6">
            <Head title={`Esquema y Configuración · ${tenantName}`} />

            {/* Selector de Sub-Módulos */}
            <div className="flex flex-wrap gap-1.5 rounded-xl border border-zinc-800 bg-[#121215] p-2">
                {(
                    [
                        {
                            key: 'schema',
                            label: 'Esquema y Filtros',
                            icon: Sliders,
                        },
                        {
                            key: 'branding',
                            label: 'Identidad Visual',
                            icon: Palette,
                        },
                        {
                            key: 'shipping',
                            label: 'Zonas de Envío',
                            icon: Truck,
                        },
                        {
                            key: 'contact',
                            label: 'WhatsApp y Bancos',
                            icon: Phone,
                        },
                        {
                            key: 'rewards',
                            label: 'Vouchers Aliados',
                            icon: Gift,
                        },
                    ] as const
                ).map((tab) => {
                    const Icon = tab.icon;
                    const active = activeTab === tab.key;
                    return (
                        <button
                            key={tab.key}
                            type="button"
                            onClick={() => setActiveTab(tab.key)}
                            className={`inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-medium transition ${
                                active
                                    ? 'bg-primary text-primary-foreground'
                                    : 'text-zinc-400 hover:bg-zinc-900 hover:text-zinc-100'
                            }`}
                        >
                            <Icon className="size-3.5" />
                            <span>{tab.label}</span>
                        </button>
                    );
                })}
            </div>

            {/* TAB 1: Constructor de metadata_schema y filter_config */}
            {activeTab === 'schema' && (
                <div className="grid grid-cols-1 gap-6 xl:grid-cols-12">
                    <div className="space-y-4 rounded-xl border border-zinc-800 bg-[#121215] p-5 xl:col-span-8">
                        <div className="flex items-center justify-between border-b border-zinc-800 pb-3">
                            <div>
                                <h2 className="text-sm font-semibold text-zinc-100">
                                    Atributos Dinámicos (metadata_schema)
                                </h2>
                                <p className="text-xs text-zinc-400">
                                    Define atributos por industria sin tocar la
                                    base de datos.
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => {
                                    const nextNumber =
                                        metadataSchema.length + 1;
                                    const defaultLabel = `Nuevo Atributo ${nextNumber}`;
                                    const otherKeys = metadataSchema.map(
                                        (a) => a.key,
                                    );
                                    const newKey = generateUniqueKey(
                                        defaultLabel,
                                        otherKeys,
                                    );
                                    setMetadataSchema((prev) => [
                                        ...prev,
                                        {
                                            key: newKey,
                                            label: defaultLabel,
                                            data_type: 'select',
                                            options: ['Opción A', 'Opción B'],
                                            is_required: false,
                                            affects_stock: true,
                                        },
                                    ]);
                                    setSchemaPage(
                                        Math.ceil(
                                            (metadataSchema.length + 1) /
                                                SCHEMA_PAGE_SIZE,
                                        ),
                                    );
                                }}
                                className="h-8 border-zinc-700 bg-zinc-900 text-xs"
                            >
                                <Plus className="mr-1 size-3.5" />
                                Añadir Atributo
                            </Button>
                        </div>

                        {metadataSchema.length === 0 ? (
                            <p className="py-8 text-center text-xs text-zinc-500">
                                Aún no has definido atributos personalizados
                                (ej. talla, color, voltaje, material).
                            </p>
                        ) : (
                            <div className="space-y-3">
                                {visibleSchemaAttrs.map((attr, pageIdx) => {
                                    const realIdx = startSchemaIdx + pageIdx;
                                    const isFacet = enabledFacetKeys.includes(
                                        attr.key,
                                    );
                                    return (
                                        <div
                                            key={realIdx}
                                            className="space-y-3 rounded-xl border border-zinc-800 bg-zinc-950/70 p-4"
                                        >
                                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                                <div>
                                                    <div className="flex items-center justify-between">
                                                        <Label className="text-[11px] text-zinc-400">
                                                            Nombre del Atributo
                                                            *
                                                        </Label>
                                                        <span className="font-mono text-[10px] text-zinc-500">
                                                            Llave:{' '}
                                                            <code className="rounded bg-zinc-900 px-1.5 py-0.5 font-semibold text-zinc-300">
                                                                {attr.key ||
                                                                    'auto'}
                                                            </code>
                                                        </span>
                                                    </div>
                                                    <Input
                                                        value={attr.label}
                                                        onChange={(e) => {
                                                            const newLabel =
                                                                e.target.value;
                                                            const copy = [
                                                                ...metadataSchema,
                                                            ];
                                                            const otherKeys =
                                                                metadataSchema
                                                                    .filter(
                                                                        (
                                                                            _,
                                                                            i,
                                                                        ) =>
                                                                            i !==
                                                                            realIdx,
                                                                    )
                                                                    .map(
                                                                        (a) =>
                                                                            a.key,
                                                                    );
                                                            const autoKey =
                                                                generateUniqueKey(
                                                                    newLabel,
                                                                    otherKeys,
                                                                    attr.key,
                                                                );
                                                            copy[realIdx] = {
                                                                ...attr,
                                                                label: newLabel,
                                                                key: autoKey,
                                                            };
                                                            setMetadataSchema(
                                                                copy,
                                                            );
                                                        }}
                                                        placeholder="Ej. Talla, Color, Material, Membrana..."
                                                        className="mt-1 h-8 border-zinc-800 bg-zinc-900 text-xs text-zinc-100"
                                                    />
                                                </div>

                                                <div>
                                                    <Label className="text-[11px] text-zinc-400">
                                                        Tipo de Dato
                                                    </Label>
                                                    <select
                                                        value={attr.data_type}
                                                        onChange={(e) => {
                                                            const nextType = e
                                                                .target
                                                                .value as MetadataDataType;
                                                            const copy = [
                                                                ...metadataSchema,
                                                            ];
                                                            copy[realIdx] = {
                                                                ...attr,
                                                                data_type:
                                                                    nextType,
                                                                options:
                                                                    nextType ===
                                                                        'select' ||
                                                                    nextType ===
                                                                        'multiselect'
                                                                        ? attr
                                                                              .options
                                                                              ?.length
                                                                            ? attr.options
                                                                            : [
                                                                                  'Opción A',
                                                                                  'Opción B',
                                                                              ]
                                                                        : null,
                                                            };
                                                            setMetadataSchema(
                                                                copy,
                                                            );
                                                        }}
                                                        className="mt-1 h-8 w-full rounded-md border border-zinc-800 bg-zinc-900 px-2.5 text-xs text-zinc-100"
                                                    >
                                                        <option value="select">
                                                            Select (1 opción)
                                                        </option>
                                                        <option value="multiselect">
                                                            Multi-Select
                                                        </option>
                                                        <option value="text">
                                                            Texto libre
                                                        </option>
                                                        <option value="number">
                                                            Numérico
                                                        </option>
                                                        <option value="boolean">
                                                            Booleano (Sí/No)
                                                        </option>
                                                        <option value="date">
                                                            Fecha
                                                        </option>
                                                    </select>
                                                </div>
                                            </div>

                                            {(attr.data_type === 'select' ||
                                                attr.data_type ===
                                                    'multiselect') && (
                                                <div>
                                                    <Label className="text-[11px] text-zinc-400">
                                                        Opciones Permitidas
                                                        (separadas por coma) *
                                                    </Label>
                                                    <Input
                                                        value={(
                                                            attr.options ?? []
                                                        ).join(', ')}
                                                        onChange={(e) => {
                                                            const opts =
                                                                e.target.value
                                                                    .split(',')
                                                                    .map((s) =>
                                                                        s.trim(),
                                                                    )
                                                                    .filter(
                                                                        Boolean,
                                                                    );
                                                            const copy = [
                                                                ...metadataSchema,
                                                            ];
                                                            copy[realIdx] = {
                                                                ...attr,
                                                                options: opts,
                                                            };
                                                            setMetadataSchema(
                                                                copy,
                                                            );
                                                        }}
                                                        placeholder="S, M, L, XL"
                                                        className="mt-1 h-8 border-zinc-800 bg-zinc-900 font-mono text-xs"
                                                    />
                                                </div>
                                            )}

                                            <div className="flex flex-wrap items-center justify-between gap-3 border-t border-zinc-800/80 pt-2.5 text-xs">
                                                <div className="flex flex-wrap items-center gap-4">
                                                    <label className="flex items-center gap-1.5 text-zinc-300">
                                                        <input
                                                            type="checkbox"
                                                            checked={Boolean(
                                                                attr.affects_stock,
                                                            )}
                                                            onChange={(e) => {
                                                                const copy = [
                                                                    ...metadataSchema,
                                                                ];
                                                                copy[realIdx] =
                                                                    {
                                                                        ...attr,
                                                                        affects_stock:
                                                                            e
                                                                                .target
                                                                                .checked,
                                                                    };
                                                                setMetadataSchema(
                                                                    copy,
                                                                );
                                                            }}
                                                        />
                                                        Nivel Variante (
                                                        affects_stock)
                                                    </label>

                                                    <label className="flex items-center gap-1.5 text-zinc-300">
                                                        <input
                                                            type="checkbox"
                                                            checked={Boolean(
                                                                attr.is_required,
                                                            )}
                                                            onChange={(e) => {
                                                                const copy = [
                                                                    ...metadataSchema,
                                                                ];
                                                                copy[realIdx] =
                                                                    {
                                                                        ...attr,
                                                                        is_required:
                                                                            e
                                                                                .target
                                                                                .checked,
                                                                    };
                                                                setMetadataSchema(
                                                                    copy,
                                                                );
                                                            }}
                                                        />
                                                        Obligatorio
                                                    </label>

                                                    <label className="flex items-center gap-1.5 text-emerald-300">
                                                        <input
                                                            type="checkbox"
                                                            checked={isFacet}
                                                            onChange={(e) => {
                                                                if (
                                                                    e.target
                                                                        .checked
                                                                ) {
                                                                    setEnabledFacetKeys(
                                                                        (p) => [
                                                                            ...p,
                                                                            attr.key,
                                                                        ],
                                                                    );
                                                                } else {
                                                                    setEnabledFacetKeys(
                                                                        (p) =>
                                                                            p.filter(
                                                                                (
                                                                                    k,
                                                                                ) =>
                                                                                    k !==
                                                                                    attr.key,
                                                                            ),
                                                                    );
                                                                }
                                                            }}
                                                        />
                                                        Exponer en Filtros de
                                                        Vitrina
                                                    </label>
                                                </div>

                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        setMetadataSchema(
                                                            (prev) =>
                                                                prev.filter(
                                                                    (_, i) =>
                                                                        i !==
                                                                        realIdx,
                                                                ),
                                                        )
                                                    }
                                                    className="text-zinc-500 hover:text-rose-400"
                                                >
                                                    <Trash2 className="size-4" />
                                                </button>
                                            </div>
                                        </div>
                                    );
                                })}

                                {/* Paginación dinámica en frontend para metadatos (solo aparece si excede 4 campos) */}
                                {metadataSchema.length > SCHEMA_PAGE_SIZE && (
                                    <div className="flex items-center justify-between border-t border-zinc-800/80 pt-3 text-xs">
                                        <span className="font-mono text-[11px] text-zinc-500">
                                            Mostrando {startSchemaIdx + 1}–
                                            {Math.min(
                                                startSchemaIdx +
                                                    SCHEMA_PAGE_SIZE,
                                                metadataSchema.length,
                                            )}{' '}
                                            de {metadataSchema.length} atributos
                                        </span>
                                        <div className="flex items-center gap-1.5">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="icon"
                                                disabled={schemaPage <= 1}
                                                onClick={() =>
                                                    setSchemaPage((p) =>
                                                        Math.max(1, p - 1),
                                                    )
                                                }
                                                className="size-7 border-zinc-800 bg-zinc-900 text-zinc-300 hover:bg-zinc-800 disabled:opacity-30"
                                                title="Atributos anteriores"
                                            >
                                                <ChevronLeft className="size-3.5" />
                                                <span className="sr-only">
                                                    Anterior
                                                </span>
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="icon"
                                                disabled={
                                                    schemaPage >=
                                                    totalSchemaPages
                                                }
                                                onClick={() =>
                                                    setSchemaPage((p) =>
                                                        Math.min(
                                                            totalSchemaPages,
                                                            p + 1,
                                                        ),
                                                    )
                                                }
                                                className="size-7 border-zinc-800 bg-zinc-900 text-zinc-300 hover:bg-zinc-800 disabled:opacity-30"
                                                title="Siguientes atributos"
                                            >
                                                <ChevronRight className="size-3.5" />
                                                <span className="sr-only">
                                                    Siguiente
                                                </span>
                                            </Button>
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}
                    </div>

                    {/* Vista Previa de Facetas Activas */}
                    <div className="space-y-4 rounded-xl border border-zinc-800 bg-[#121215] p-5 xl:col-span-4">
                        <h3 className="text-sm font-semibold text-zinc-100">
                            Facetas de Vitrina (filter_config)
                        </h3>
                        <label className="flex items-center justify-between rounded-lg border border-zinc-800 bg-zinc-950 p-3 text-xs">
                            <span>Filtro de Rango de Precio</span>
                            <input
                                type="checkbox"
                                checked={enablePriceFilter}
                                onChange={(e) =>
                                    setEnablePriceFilter(e.target.checked)
                                }
                            />
                        </label>

                        <div className="space-y-2 rounded-lg border border-zinc-800/80 bg-zinc-950/60 p-3 text-xs">
                            <p className="font-mono text-[10px] tracking-wider text-zinc-500 uppercase">
                                Vista Previa del Sidebar Público
                            </p>
                            {enablePriceFilter && (
                                <div className="rounded border border-zinc-800 bg-[#121215] px-2.5 py-1.5 text-zinc-300">
                                    Rango de Precio ({currencyCode})
                                </div>
                            )}
                            {metadataSchema
                                .filter((a) => enabledFacetKeys.includes(a.key))
                                .map((a) => (
                                    <div
                                        key={a.key}
                                        className="rounded border border-indigo-500/30 bg-indigo-500/10 px-2.5 py-1.5 text-indigo-200"
                                    >
                                        {a.label}{' '}
                                        <span className="font-mono text-[10px] opacity-70">
                                            [{a.data_type}]
                                        </span>
                                    </div>
                                ))}
                        </div>

                        <Button
                            type="button"
                            disabled={isSaving}
                            onClick={() => void handleSaveSchema()}
                            className="w-full gap-1.5 bg-indigo-500 text-xs font-semibold text-white hover:bg-indigo-400"
                        >
                            <Save className="size-3.5" />
                            <span>Guardar Esquema y Filtros</span>
                        </Button>
                    </div>
                </div>
            )}

            {/* TAB 2: Identidad Visual (Branding) */}
            {activeTab === 'branding' && (
                <div className="max-w-2xl space-y-4 rounded-xl border border-zinc-800 bg-[#121215] p-5">
                    <h2 className="text-sm font-semibold text-zinc-100">
                        Tokens de Marca Dinámicos (RF-03)
                    </h2>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <Label className="text-xs text-zinc-300">
                                Color Primario (#HEX)
                            </Label>
                            <div className="mt-1 flex gap-2">
                                <input
                                    type="color"
                                    value={primaryColor}
                                    onChange={(e) =>
                                        setPrimaryColor(e.target.value)
                                    }
                                    className="h-9 w-11 cursor-pointer rounded border border-zinc-800 bg-zinc-950"
                                />
                                <Input
                                    value={primaryColor}
                                    onChange={(e) =>
                                        setPrimaryColor(e.target.value)
                                    }
                                    className="h-9 border-zinc-800 bg-zinc-950 font-mono text-xs"
                                />
                            </div>
                        </div>

                        <div>
                            <Label className="text-xs text-zinc-300">
                                Color Secundario
                            </Label>
                            <div className="mt-1 flex gap-2">
                                <input
                                    type="color"
                                    value={secondaryColor}
                                    onChange={(e) =>
                                        setSecondaryColor(e.target.value)
                                    }
                                    className="h-9 w-11 cursor-pointer rounded border border-zinc-800 bg-zinc-950"
                                />
                                <Input
                                    value={secondaryColor}
                                    onChange={(e) =>
                                        setSecondaryColor(e.target.value)
                                    }
                                    className="h-9 border-zinc-800 bg-zinc-950 font-mono text-xs"
                                />
                            </div>
                        </div>

                        <div>
                            <Label className="text-xs text-zinc-300">
                                Color Acento
                            </Label>
                            <div className="mt-1 flex gap-2">
                                <input
                                    type="color"
                                    value={accentColor}
                                    onChange={(e) =>
                                        setAccentColor(e.target.value)
                                    }
                                    className="h-9 w-11 cursor-pointer rounded border border-zinc-800 bg-zinc-950"
                                />
                                <Input
                                    value={accentColor}
                                    onChange={(e) =>
                                        setAccentColor(e.target.value)
                                    }
                                    className="h-9 border-zinc-800 bg-zinc-950 font-mono text-xs"
                                />
                            </div>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <Label className="text-xs text-zinc-300">
                                Tipografía Base
                            </Label>
                            <Input
                                value={fontFamily}
                                onChange={(e) => setFontFamily(e.target.value)}
                                placeholder="Instrument Sans"
                                className="mt-1 border-zinc-800 bg-zinc-950 text-xs"
                            />
                        </div>
                        <div>
                            <Label className="text-xs text-zinc-300">
                                Logo URL (https://)
                            </Label>
                            <Input
                                value={logoUrl}
                                onChange={(e) => setLogoUrl(e.target.value)}
                                placeholder="https://..."
                                className="mt-1 border-zinc-800 bg-zinc-950 font-mono text-xs"
                            />
                        </div>
                        <div className="sm:col-span-2">
                            <Label className="text-xs text-zinc-300">
                                Banner Hero URL (https://)
                            </Label>
                            <Input
                                value={bannerUrl}
                                onChange={(e) => setBannerUrl(e.target.value)}
                                placeholder="https://..."
                                className="mt-1 border-zinc-800 bg-zinc-950 font-mono text-xs"
                            />
                        </div>
                    </div>

                    <Button
                        type="button"
                        disabled={isSaving}
                        onClick={() => void handleSaveBranding()}
                        className="gap-1.5 bg-indigo-500 text-xs font-semibold text-white"
                    >
                        <Save className="size-3.5" />
                        Guardar Identidad Visual
                    </Button>
                </div>
            )}

            {/* TAB 3: Zonas de Envío */}
            {activeTab === 'shipping' && (
                <div className="max-w-2xl space-y-4 rounded-xl border border-zinc-800 bg-[#121215] p-5">
                    <div className="flex items-center justify-between">
                        <h2 className="text-sm font-semibold text-zinc-100">
                            Zonas y Tarifas de Cobertura ({currencyCode})
                        </h2>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() =>
                                setShippingZones((prev) => [
                                    ...prev,
                                    {
                                        code: `ZONA_${prev.length + 1}`,
                                        name: 'Nueva Zona',
                                        fee: 80,
                                    },
                                ])
                            }
                            className="h-8 border-zinc-700 bg-zinc-900 text-xs"
                        >
                            <Plus className="mr-1 size-3.5" />
                            Añadir Zona
                        </Button>
                    </div>

                    <div className="space-y-2.5">
                        {shippingZones.map((zone, idx) => (
                            <div
                                key={idx}
                                className="grid grid-cols-1 gap-2 rounded-lg border border-zinc-800 bg-zinc-950 p-3 sm:grid-cols-[120px_1fr_120px_auto]"
                            >
                                <Input
                                    value={zone.code}
                                    onChange={(e) => {
                                        const copy = [...shippingZones];
                                        copy[idx] = {
                                            ...zone,
                                            code: e.target.value.toUpperCase(),
                                        };
                                        setShippingZones(copy);
                                    }}
                                    placeholder="CÓDIGO"
                                    className="h-8 border-zinc-800 bg-zinc-900 font-mono text-xs"
                                />
                                <Input
                                    value={zone.name}
                                    onChange={(e) => {
                                        const copy = [...shippingZones];
                                        copy[idx] = {
                                            ...zone,
                                            name: e.target.value,
                                        };
                                        setShippingZones(copy);
                                    }}
                                    placeholder="Nombre de ciudad / región"
                                    className="h-8 border-zinc-800 bg-zinc-900 text-xs"
                                />
                                <Input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={zone.fee}
                                    onChange={(e) => {
                                        const copy = [...shippingZones];
                                        copy[idx] = {
                                            ...zone,
                                            fee: Number(e.target.value),
                                        };
                                        setShippingZones(copy);
                                    }}
                                    className="h-8 border-zinc-800 bg-zinc-900 font-mono text-xs"
                                />
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    onClick={() =>
                                        setShippingZones((prev) =>
                                            prev.filter((_, i) => i !== idx),
                                        )
                                    }
                                    className="size-8 text-zinc-500 hover:text-rose-400"
                                >
                                    <Trash2 className="size-3.5" />
                                </Button>
                            </div>
                        ))}
                    </div>

                    <Button
                        type="button"
                        disabled={isSaving}
                        onClick={() => void handleSaveShipping()}
                        className="gap-1.5 bg-indigo-500 text-xs font-semibold text-white"
                    >
                        <Save className="size-3.5" />
                        Guardar Zonas de Envío
                    </Button>
                </div>
            )}

            {/* TAB 4: Contacto WhatsApp y Cuentas Bancarias */}
            {activeTab === 'contact' && (
                <div className="max-w-4xl space-y-5 rounded-xl border border-zinc-800 bg-[#121215] p-5">
                    <h2 className="text-sm font-semibold text-zinc-100">
                        Número Oficial de Cierre en WhatsApp y Cuentas de Cobro
                    </h2>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <Label className="text-xs text-zinc-300">
                                WhatsApp de Ventas (con código de país)
                            </Label>
                            <Input
                                value={whatsappSales}
                                onChange={(e) =>
                                    setWhatsappSales(e.target.value)
                                }
                                placeholder="50499887766"
                                className="mt-1 border-zinc-800 bg-zinc-950 font-mono text-xs"
                            />
                        </div>
                        <div>
                            <Label className="text-xs text-zinc-300">
                                Correo de Soporte
                            </Label>
                            <Input
                                type="email"
                                value={supportEmail}
                                onChange={(e) =>
                                    setSupportEmail(e.target.value)
                                }
                                placeholder="ventas@tienda.com"
                                className="mt-1 border-zinc-800 bg-zinc-950 text-xs"
                            />
                        </div>
                    </div>

                    <div className="space-y-3 border-t border-zinc-800 pt-4">
                        <div className="flex items-center justify-between">
                            <div>
                                <h3 className="text-xs font-semibold text-zinc-200">
                                    Cuentas Bancarias para Transferencia
                                </h3>
                                <p className="text-[11px] text-zinc-400">
                                    Información de cobro bancario mostrada al
                                    cliente durante el checkout y cierre por
                                    WhatsApp.
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    setBankAccounts((prev) => [
                                        ...prev,
                                        {
                                            bank_name: 'BAC Credomatic',
                                            account_number: '',
                                            account_type: 'Ahorro HNL',
                                            holder_name: tenantName,
                                        },
                                    ])
                                }
                                className="h-8 border-zinc-700 bg-zinc-900 text-xs font-medium text-zinc-200 hover:bg-zinc-800"
                            >
                                <Plus className="mr-1.5 size-3.5" />
                                Añadir Cuenta
                            </Button>
                        </div>

                        {bankAccounts.length === 0 ? (
                            <div className="rounded-xl border border-dashed border-zinc-800 p-6 text-center text-xs text-zinc-500">
                                No hay cuentas bancarias registradas. Haz clic
                                en &quot;Añadir Cuenta&quot; para registrar la
                                primera.
                            </div>
                        ) : (
                            <div className="overflow-x-auto rounded-xl border border-zinc-800 bg-zinc-950">
                                <table className="w-full text-left text-xs">
                                    <thead className="border-b border-zinc-800 bg-zinc-900/80 font-mono text-[10px] tracking-wider text-zinc-400 uppercase">
                                        <tr>
                                            <th className="px-3.5 py-2.5 font-medium">
                                                Banco / Institución
                                            </th>
                                            <th className="px-3.5 py-2.5 font-medium">
                                                Número de Cuenta
                                            </th>
                                            <th className="px-3.5 py-2.5 font-medium">
                                                Tipo de Cuenta
                                            </th>
                                            <th className="px-3.5 py-2.5 font-medium">
                                                Titular de Cuenta
                                            </th>
                                            <th className="w-12 px-2 py-2.5 text-center font-medium">
                                                Acciones
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-zinc-800/70">
                                        {bankAccounts.map((acc, idx) => (
                                            <tr
                                                key={idx}
                                                className="transition hover:bg-zinc-900/30"
                                            >
                                                <td className="p-2.5">
                                                    <Input
                                                        value={acc.bank_name}
                                                        onChange={(e) => {
                                                            const copy = [
                                                                ...bankAccounts,
                                                            ];
                                                            copy[idx] = {
                                                                ...acc,
                                                                bank_name:
                                                                    e.target
                                                                        .value,
                                                            };
                                                            setBankAccounts(
                                                                copy,
                                                            );
                                                        }}
                                                        placeholder="Ej. BAC Credomatic"
                                                        className="h-8 border-zinc-800 bg-zinc-900 text-xs text-zinc-100 placeholder:text-zinc-600"
                                                    />
                                                </td>
                                                <td className="p-2.5">
                                                    <Input
                                                        value={
                                                            acc.account_number
                                                        }
                                                        onChange={(e) => {
                                                            const copy = [
                                                                ...bankAccounts,
                                                            ];
                                                            copy[idx] = {
                                                                ...acc,
                                                                account_number:
                                                                    e.target
                                                                        .value,
                                                            };
                                                            setBankAccounts(
                                                                copy,
                                                            );
                                                        }}
                                                        placeholder="Ej. 748291044"
                                                        className="h-8 border-zinc-800 bg-zinc-900 font-mono text-xs text-zinc-100 placeholder:text-zinc-600"
                                                    />
                                                </td>
                                                <td className="p-2.5">
                                                    <Input
                                                        value={acc.account_type}
                                                        onChange={(e) => {
                                                            const copy = [
                                                                ...bankAccounts,
                                                            ];
                                                            copy[idx] = {
                                                                ...acc,
                                                                account_type:
                                                                    e.target
                                                                        .value,
                                                            };
                                                            setBankAccounts(
                                                                copy,
                                                            );
                                                        }}
                                                        placeholder="Ej. Cheques / Ahorro"
                                                        className="h-8 border-zinc-800 bg-zinc-900 text-xs text-zinc-100 placeholder:text-zinc-600"
                                                    />
                                                </td>
                                                <td className="p-2.5">
                                                    <Input
                                                        value={acc.holder_name}
                                                        onChange={(e) => {
                                                            const copy = [
                                                                ...bankAccounts,
                                                            ];
                                                            copy[idx] = {
                                                                ...acc,
                                                                holder_name:
                                                                    e.target
                                                                        .value,
                                                            };
                                                            setBankAccounts(
                                                                copy,
                                                            );
                                                        }}
                                                        placeholder="Nombre del Titular"
                                                        className="h-8 border-zinc-800 bg-zinc-900 text-xs text-zinc-100 placeholder:text-zinc-600"
                                                    />
                                                </td>
                                                <td className="p-2.5 text-center">
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() =>
                                                            setBankAccounts(
                                                                (prev) =>
                                                                    prev.filter(
                                                                        (
                                                                            _,
                                                                            i,
                                                                        ) =>
                                                                            i !==
                                                                            idx,
                                                                    ),
                                                            )
                                                        }
                                                        className="size-8 text-zinc-500 hover:bg-rose-500/10 hover:text-rose-400"
                                                        title="Eliminar cuenta"
                                                    >
                                                        <Trash2 className="size-3.5" />
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>

                    <Button
                        type="button"
                        disabled={isSaving}
                        onClick={() => void handleSaveContact()}
                        className="gap-1.5 bg-indigo-500 text-xs font-semibold text-white"
                    >
                        <Save className="size-3.5" />
                        Guardar Contacto y Cuentas
                    </Button>
                </div>
            )}

            {/* TAB 5: Reglas de Vouchers Aliados (Outbound Rewards) */}
            {activeTab === 'rewards' && (
                <div className="max-w-xl space-y-4 rounded-xl border border-zinc-800 bg-[#121215] p-5">
                    <h2 className="text-sm font-semibold text-zinc-100">
                        Emisión Automática de Recompensas Cruzadas
                    </h2>
                    <p className="text-xs text-zinc-400">
                        Al marcar una orden como COMPLETADA por encima del monto
                        mínimo, el comprador recibe un voucher canjeable en el
                        comercio aliado seleccionado.
                    </p>

                    <label className="flex items-center justify-between rounded-lg border border-zinc-800 bg-zinc-950 p-3 text-xs">
                        <span className="font-medium text-zinc-200">
                            Activar emisión de Vouchers Aliados
                        </span>
                        <input
                            type="checkbox"
                            checked={rewardsEnabled}
                            onChange={(e) =>
                                setRewardsEnabled(e.target.checked)
                            }
                        />
                    </label>

                    {rewardsEnabled && (
                        <div className="space-y-3">
                            <div>
                                <Label className="text-xs text-zinc-300">
                                    Comercio Aliado Destino *
                                </Label>
                                <select
                                    value={rewardPartnerId}
                                    onChange={(e) =>
                                        setRewardPartnerId(e.target.value)
                                    }
                                    className="mt-1 h-9 w-full rounded-md border border-zinc-800 bg-zinc-950 px-3 text-xs text-zinc-100"
                                >
                                    <option value="">
                                        Seleccionar comercio aliado...
                                    </option>
                                    {partners
                                        .filter((p) => p.is_active)
                                        .map((p) => (
                                            <option key={p.id} value={p.id}>
                                                {p.name} — {p.benefit_title}
                                            </option>
                                        ))}
                                </select>
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <Label className="text-xs text-zinc-300">
                                        Compra Mínima ({currencyCode})
                                    </Label>
                                    <Input
                                        type="number"
                                        min="0"
                                        value={rewardMinAmount}
                                        onChange={(e) =>
                                            setRewardMinAmount(e.target.value)
                                        }
                                        className="mt-1 border-zinc-800 bg-zinc-950 font-mono text-xs"
                                    />
                                </div>
                                <div>
                                    <Label className="text-xs text-zinc-300">
                                        Días de Vigencia
                                    </Label>
                                    <Input
                                        type="number"
                                        min="1"
                                        value={rewardValidityDays}
                                        onChange={(e) =>
                                            setRewardValidityDays(
                                                e.target.value,
                                            )
                                        }
                                        className="mt-1 border-zinc-800 bg-zinc-950 font-mono text-xs"
                                    />
                                </div>
                            </div>
                        </div>
                    )}

                    <Button
                        type="button"
                        disabled={isSaving}
                        onClick={() => void handleSaveRewards()}
                        className="gap-1.5 bg-indigo-500 text-xs font-semibold text-white"
                    >
                        <Save className="size-3.5" />
                        Guardar Reglas de Recompensa
                    </Button>
                </div>
            )}
        </div>
    );
}

export default function TenantAdminSchemaSettingsPage(
    props: PortalShellPageProps,
) {
    return (
        <TenantAdminLayout
            tenantSlug={props.tenantSlug}
            tenantName={props.tenantName}
            currencyCode={props.currencyCode}
            countryCode={props.countryCode}
            urlMode={props.urlMode}
            activeSection="schema"
            title="Constructor de Esquema y Configuración"
            subtitle="Gobierna atributos dinámicos, facetas de vitrina, branding, zonas y recompensas"
        >
            <SchemaSettingsContent />
        </TenantAdminLayout>
    );
}
