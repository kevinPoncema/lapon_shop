import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type MetadataAttributeDefinition } from '@/types/commerce';

interface DynamicMetadataFieldsProps {
    schema: MetadataAttributeDefinition[];
    affectsStock: boolean;
    value: Record<string, unknown>;
    onChange: (next: Record<string, unknown>) => void;
    compact?: boolean;
}

export function DynamicMetadataFields({
    schema,
    affectsStock,
    value,
    onChange,
    compact = false,
}: DynamicMetadataFieldsProps) {
    const matchingAttributes = schema.filter(
        (attr) => Boolean(attr.affects_stock) === affectsStock,
    );

    if (matchingAttributes.length === 0) {
        return null;
    }

    const updateKey = (key: string, nextVal: unknown) => {
        const copy = { ...value };
        if (
            nextVal === undefined ||
            nextVal === null ||
            nextVal === '' ||
            (Array.isArray(nextVal) && nextVal.length === 0)
        ) {
            delete copy[key];
        } else {
            copy[key] = nextVal;
        }
        onChange(copy);
    };

    const toggleMultiselect = (key: string, opt: string) => {
        const current = Array.isArray(value[key])
            ? (value[key] as string[])
            : [];
        const next = current.includes(opt)
            ? current.filter((i) => i !== opt)
            : [...current, opt];
        updateKey(key, next);
    };

    return (
        <div
            className={`grid grid-cols-1 gap-3 ${compact ? 'sm:grid-cols-3' : 'sm:grid-cols-2'}`}
        >
            {matchingAttributes.map((attr) => {
                const rawVal = value[attr.key];

                return (
                    <div key={attr.key} className="space-y-1">
                        <Label className="flex items-center gap-1 text-[11px] text-zinc-300">
                            <span>{attr.label}</span>
                            {attr.is_required && (
                                <span className="text-rose-400">*</span>
                            )}
                            <span className="font-mono text-[9px] text-zinc-500">
                                ({attr.key})
                            </span>
                        </Label>

                        {attr.data_type === 'select' ? (
                            <select
                                required={Boolean(attr.is_required)}
                                value={typeof rawVal === 'string' ? rawVal : ''}
                                onChange={(e) =>
                                    updateKey(attr.key, e.target.value)
                                }
                                className="h-8 w-full rounded-md border border-zinc-800 bg-zinc-950 px-2.5 text-xs text-zinc-100 focus:border-primary focus:outline-none"
                            >
                                <option value="">Seleccionar...</option>
                                {(attr.options ?? []).map((opt) => (
                                    <option key={opt} value={opt}>
                                        {opt}
                                    </option>
                                ))}
                            </select>
                        ) : attr.data_type === 'multiselect' ? (
                            <div className="flex flex-wrap gap-1 pt-0.5">
                                {(attr.options ?? []).map((opt) => {
                                    const active =
                                        Array.isArray(rawVal) &&
                                        rawVal.includes(opt);
                                    return (
                                        <button
                                            key={opt}
                                            type="button"
                                            onClick={() =>
                                                toggleMultiselect(attr.key, opt)
                                            }
                                            className={`rounded border px-2 py-0.5 text-[11px] transition ${
                                                active
                                                    ? 'border-primary bg-primary/20 text-zinc-50'
                                                    : 'border-zinc-800 bg-zinc-950 text-zinc-400'
                                            }`}
                                        >
                                            {opt}
                                        </button>
                                    );
                                })}
                            </div>
                        ) : attr.data_type === 'boolean' ? (
                            <select
                                value={
                                    rawVal === true
                                        ? 'true'
                                        : rawVal === false
                                          ? 'false'
                                          : ''
                                }
                                onChange={(e) => {
                                    const v = e.target.value;
                                    updateKey(
                                        attr.key,
                                        v === '' ? undefined : v === 'true',
                                    );
                                }}
                                className="h-8 w-full rounded-md border border-zinc-800 bg-zinc-950 px-2.5 text-xs text-zinc-100"
                            >
                                <option value="">Sin especificar</option>
                                <option value="true">Sí</option>
                                <option value="false">No</option>
                            </select>
                        ) : (
                            <Input
                                required={Boolean(attr.is_required)}
                                type={
                                    attr.data_type === 'number'
                                        ? 'number'
                                        : attr.data_type === 'date'
                                          ? 'date'
                                          : 'text'
                                }
                                value={
                                    typeof rawVal === 'string' ||
                                    typeof rawVal === 'number'
                                        ? String(rawVal)
                                        : ''
                                }
                                onChange={(e) =>
                                    updateKey(
                                        attr.key,
                                        attr.data_type === 'number'
                                            ? e.target.value === ''
                                                ? undefined
                                                : Number(e.target.value)
                                            : e.target.value,
                                    )
                                }
                                className="h-8 border-zinc-800 bg-zinc-950 text-xs text-zinc-100"
                            />
                        )}
                    </div>
                );
            })}
        </div>
    );
}
