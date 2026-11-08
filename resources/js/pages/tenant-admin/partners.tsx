import { Head, Link } from '@inertiajs/react';
import { ExternalLink, Handshake, Loader2, Plus, QrCode } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';
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
import { portalPaths } from '@/lib/portal-paths';
import {
    type PartnerStoreAdmin,
    type PortalShellPageProps,
} from '@/types/commerce';

function PartnersAdminContent() {
    const { tenantSlug, tenantName, routeCtx } = useTenant();

    const [partners, setPartners] = useState<PartnerStoreAdmin[]>([]);
    const [isLoading, setIsLoading] = useState<boolean>(true);

    const [modalOpen, setModalOpen] = useState<boolean>(false);
    const [name, setName] = useState<string>('');
    const [portalEmail, setPortalEmail] = useState<string>('');
    const [portalPassword, setPortalPassword] = useState<string>('');
    const [benefitTitle, setBenefitTitle] = useState<string>('');
    const [termsConditions, setTermsConditions] = useState<string>('');
    const [isSaving, setIsSaving] = useState<boolean>(false);

    const loadPartners = useCallback(async () => {
        setIsLoading(true);
        try {
            const res = await commerceApi.admin.getPartnerStores(tenantSlug);
            setPartners(res.data);
        } catch (err) {
            toast.error(
                err instanceof ApiClientError
                    ? err.message
                    : 'Error al cargar comercios aliados.',
            );
        } finally {
            setIsLoading(false);
        }
    }, [tenantSlug]);

    useEffect(() => {
        void loadPartners();
    }, [loadPartners]);

    const handleCreatePartner = async (e: React.FormEvent) => {
        e.preventDefault();
        if (isSaving) {
            return;
        }
        setIsSaving(true);
        try {
            await commerceApi.admin.createPartnerStore(tenantSlug, {
                name: name.trim(),
                portal_email: portalEmail.trim(),
                portal_password: portalPassword,
                benefit_title: benefitTitle.trim(),
                terms_conditions:
                    termsConditions.trim() ||
                    'Válido presentando código en mostrador.',
                is_active: true,
            });
            toast.success('Comercio aliado registrado con acceso POS.');
            setModalOpen(false);
            setName('');
            setPortalEmail('');
            setPortalPassword('');
            setBenefitTitle('');
            setTermsConditions('');
            await loadPartners();
        } catch (err) {
            toast.error(
                err instanceof ApiClientError
                    ? (err.firstFieldError() ?? err.message)
                    : 'Error al crear comercio aliado.',
            );
        } finally {
            setIsSaving(false);
        }
    };

    const handleToggleActive = async (partner: PartnerStoreAdmin) => {
        try {
            await commerceApi.admin.updatePartnerStore(tenantSlug, partner.id, {
                is_active: !partner.is_active,
            });
            toast.success(
                !partner.is_active
                    ? 'Acceso aliado habilitado.'
                    : 'Acceso aliado revocado.',
            );
            await loadPartners();
        } catch (err) {
            toast.error(
                err instanceof ApiClientError
                    ? err.message
                    : 'No se pudo actualizar el comercio aliado.',
            );
        }
    };

    return (
        <div>
            <Head title={`Comercios Aliados · ${tenantName}`} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <Button
                    asChild
                    variant="outline"
                    size="sm"
                    className="h-9 border-zinc-800 bg-[#121215] text-xs text-zinc-200"
                >
                    <Link href={portalPaths.partner.pos(routeCtx)}>
                        <QrCode className="mr-1.5 size-3.5 text-emerald-400" />
                        Abrir Portal POS de Aliados
                        <ExternalLink className="ml-1.5 size-3 text-zinc-500" />
                    </Link>
                </Button>

                <Button
                    type="button"
                    size="sm"
                    onClick={() => setModalOpen(true)}
                    className="h-9 bg-indigo-500 text-xs font-semibold text-white"
                >
                    <Plus className="mr-1.5 size-3.5" />
                    Registrar Comercio Aliado
                </Button>
            </div>

            {isLoading ? (
                <div className="flex items-center justify-center rounded-xl border border-zinc-800 bg-[#121215] py-16 text-xs text-zinc-400">
                    <Loader2 className="mr-2 size-4 animate-spin text-primary" />
                    Cargando red de comercios aliados...
                </div>
            ) : partners.length === 0 ? (
                <div className="rounded-xl border border-dashed border-zinc-800 bg-[#121215]/60 p-12 text-center">
                    <Handshake className="mx-auto size-10 text-zinc-600" />
                    <p className="mt-3 text-sm font-medium text-zinc-200">
                        Sin comercios aliados configurados
                    </p>
                    <p className="mt-1 text-xs text-zinc-400">
                        Crea alianzas cruzadas (cafeterías, gimnasios, estudios)
                        para premiar órdenes completadas.
                    </p>
                </div>
            ) : (
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    {partners.map((p) => (
                        <div
                            key={p.id}
                            className="flex flex-col justify-between rounded-xl border border-zinc-800 bg-[#121215] p-5"
                        >
                            <div>
                                <div className="flex items-start justify-between gap-2">
                                    <div>
                                        <h2 className="text-base font-semibold text-zinc-100">
                                            {p.name}
                                        </h2>
                                        <p className="font-mono text-xs text-zinc-500">
                                            POS Login: {p.portal_email}
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            void handleToggleActive(p)
                                        }
                                        className={`rounded-full border px-2.5 py-0.5 font-mono text-[10px] font-semibold ${
                                            p.is_active
                                                ? 'border-emerald-500/30 bg-emerald-500/15 text-emerald-300'
                                                : 'border-zinc-700 bg-zinc-900 text-zinc-500'
                                        }`}
                                    >
                                        {p.is_active ? 'ACTIVO' : 'SUSPENDIDO'}
                                    </button>
                                </div>

                                <div className="mt-3 rounded-lg border border-zinc-800/80 bg-zinc-950/70 p-3">
                                    <p className="text-xs font-semibold text-emerald-300">
                                        {p.benefit_title}
                                    </p>
                                    <p className="mt-1 text-[11px] text-zinc-400">
                                        {p.terms_conditions}
                                    </p>
                                </div>
                            </div>

                            <div className="mt-4 flex items-center justify-between border-t border-zinc-800/80 pt-3 font-mono text-xs text-zinc-400">
                                <span>
                                    Emitidos:{' '}
                                    <strong className="text-zinc-100">
                                        {p.vouchers_count ?? 0}
                                    </strong>
                                </span>
                                <span>
                                    Canjeados:{' '}
                                    <strong className="text-emerald-400">
                                        {p.redeemed_count ?? 0}
                                    </strong>
                                </span>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            <Dialog open={modalOpen} onOpenChange={setModalOpen}>
                <DialogContent className="border-zinc-800 bg-[#121215] text-zinc-100 sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Registrar Comercio Aliado</DialogTitle>
                        <DialogDescription className="text-xs text-zinc-400">
                            Se creará una credencial exclusiva{' '}
                            <code className="font-mono">partner-portal</code>{' '}
                            sin acceso a este panel administrativo.
                        </DialogDescription>
                    </DialogHeader>

                    <form
                        onSubmit={(e) => void handleCreatePartner(e)}
                        className="space-y-3"
                    >
                        <div>
                            <Label className="text-xs text-zinc-300">
                                Nombre del Comercio Aliado *
                            </Label>
                            <Input
                                required
                                maxLength={150}
                                value={name}
                                onChange={(e) => setName(e.target.value)}
                                placeholder="Ej. Espresso Lab Specialty Coffee"
                                className="mt-1 border-zinc-800 bg-zinc-950 text-xs"
                            />
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <Label className="text-xs text-zinc-300">
                                    Correo Acceso POS *
                                </Label>
                                <Input
                                    type="email"
                                    required
                                    value={portalEmail}
                                    onChange={(e) =>
                                        setPortalEmail(e.target.value)
                                    }
                                    placeholder="caja@aliado.com"
                                    className="mt-1 border-zinc-800 bg-zinc-950 text-xs"
                                />
                            </div>
                            <div>
                                <Label className="text-xs text-zinc-300">
                                    Contraseña POS (mín 6) *
                                </Label>
                                <Input
                                    type="password"
                                    required
                                    minLength={6}
                                    value={portalPassword}
                                    onChange={(e) =>
                                        setPortalPassword(e.target.value)
                                    }
                                    className="mt-1 border-zinc-800 bg-zinc-950 text-xs"
                                />
                            </div>
                        </div>

                        <div>
                            <Label className="text-xs text-zinc-300">
                                Beneficio Otorgado al Comprador *
                            </Label>
                            <Input
                                required
                                maxLength={190}
                                value={benefitTitle}
                                onChange={(e) =>
                                    setBenefitTitle(e.target.value)
                                }
                                placeholder="Ej. Cold Brew de Cortesía o 25% OFF en barra"
                                className="mt-1 border-zinc-800 bg-zinc-950 text-xs"
                            />
                        </div>

                        <div>
                            <Label className="text-xs text-zinc-300">
                                Términos y Condiciones *
                            </Label>
                            <Input
                                required
                                value={termsConditions}
                                onChange={(e) =>
                                    setTermsConditions(e.target.value)
                                }
                                placeholder="Un canje por voucher, válido en horario regular."
                                className="mt-1 border-zinc-800 bg-zinc-950 text-xs"
                            />
                        </div>

                        <Button
                            type="submit"
                            disabled={isSaving}
                            className="w-full bg-indigo-500 text-xs font-semibold text-white"
                        >
                            {isSaving
                                ? 'Registrando...'
                                : 'Habilitar Comercio Aliado'}
                        </Button>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    );
}

export default function TenantAdminPartnersPage(props: PortalShellPageProps) {
    return (
        <TenantAdminLayout
            tenantSlug={props.tenantSlug}
            tenantName={props.tenantName}
            currencyCode={props.currencyCode}
            countryCode={props.countryCode}
            urlMode={props.urlMode}
            activeSection="partners"
            title="Comercios Aliados y Red Cruzada"
            subtitle="Credenciales POS aisladas y auditoría de emisión/canje de vouchers"
        >
            <PartnersAdminContent />
        </TenantAdminLayout>
    );
}
