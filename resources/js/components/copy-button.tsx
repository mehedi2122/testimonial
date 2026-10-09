import { Check, Copy } from 'lucide-react';
import { useState } from 'react';
import type { ComponentProps } from 'react';
import { Button } from '@/components/ui/button';

type Props = Omit<ComponentProps<typeof Button>, 'onClick'> & {
    value: string;
    label?: string;
};

/**
 * Copy-to-clipboard button with a 2-second "Copied" flash (PRD §34).
 * Falls back to a hidden textarea + execCommand where the async
 * clipboard API is unavailable (non-secure origins).
 */
export function CopyButton({
    value,
    label = 'Copy',
    size = 'sm',
    variant = 'outline',
    ...props
}: Props) {
    const [copied, setCopied] = useState(false);

    const copy = async (): Promise<void> => {
        try {
            if (navigator.clipboard?.writeText) {
                await navigator.clipboard.writeText(value);
            } else {
                const textarea = document.createElement('textarea');
                textarea.value = value;
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
            }
            setCopied(true);
            window.setTimeout(() => setCopied(false), 2000);
        } catch {
            // Silently no-op — the value is visible next to the button,
            // so it can still be copied by hand.
        }
    };

    return (
        <Button
            type="button"
            size={size}
            variant={variant}
            onClick={copy}
            {...props}
        >
            {copied ? (
                <>
                    <Check className="size-4" />
                    Copied
                </>
            ) : (
                <>
                    <Copy className="size-4" />
                    {label}
                </>
            )}
        </Button>
    );
}
