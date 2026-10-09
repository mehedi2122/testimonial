import { cn } from '@/lib/utils';

export type FieldMode = 'off' | 'optional' | 'required';

export type EditableField = {
    field_key: string;
    label: string;
    type: string;
    mode: FieldMode;
};

type Props = {
    fields: EditableField[];
    value: Record<string, FieldMode>;
    onChange: (value: Record<string, FieldMode>) => void;
};

const MODES: { value: FieldMode; label: string }[] = [
    { value: 'off', label: 'Off' },
    { value: 'optional', label: 'Optional' },
    { value: 'required', label: 'Required' },
];

/**
 * Field configuration for the public form (PRD §8). Name and Email are
 * always collected; every other field is Off, Optional or Required.
 */
export function FieldModesEditor({ fields, value, onChange }: Props) {
    return (
        <div className="grid gap-2">
            <div>
                <p className="text-sm font-medium">Form fields</p>
                <p className="text-xs text-muted-foreground">
                    Choose what customers fill in on your public page.
                </p>
            </div>
            <ul className="divide-y rounded-md border">
                <FixedRow label="Name" />
                <FixedRow label="Email address" note="never shown publicly" />
                {fields.map((field) => {
                    const current = value[field.field_key] ?? field.mode;

                    return (
                        <li
                            key={field.field_key}
                            className="flex flex-wrap items-center justify-between gap-2 px-3 py-2"
                        >
                            <span className="text-sm">
                                {field.label}
                                {field.type === 'image' && (
                                    <span className="ml-1 text-xs text-muted-foreground">
                                        (photo upload)
                                    </span>
                                )}
                            </span>
                            <div
                                role="radiogroup"
                                aria-label={`${field.label} setting`}
                                className="inline-flex rounded-md border p-0.5"
                            >
                                {MODES.map((mode) => (
                                    <button
                                        key={mode.value}
                                        type="button"
                                        role="radio"
                                        aria-checked={current === mode.value}
                                        onClick={() =>
                                            onChange({
                                                ...value,
                                                [field.field_key]: mode.value,
                                            })
                                        }
                                        className={cn(
                                            'rounded px-2.5 py-1 text-xs font-medium transition focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                            current === mode.value
                                                ? 'bg-primary text-primary-foreground'
                                                : 'text-muted-foreground hover:text-foreground',
                                        )}
                                    >
                                        {mode.label}
                                    </button>
                                ))}
                            </div>
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}

function FixedRow({ label, note }: { label: string; note?: string }) {
    return (
        <li className="flex flex-wrap items-center justify-between gap-2 px-3 py-2">
            <span className="text-sm">
                {label}
                {note && (
                    <span className="ml-1 text-xs text-muted-foreground">
                        ({note})
                    </span>
                )}
            </span>
            <span className="text-xs text-muted-foreground">
                Always required
            </span>
        </li>
    );
}

export function modesFrom(fields: EditableField[]): Record<string, FieldMode> {
    return Object.fromEntries(fields.map((f) => [f.field_key, f.mode]));
}
