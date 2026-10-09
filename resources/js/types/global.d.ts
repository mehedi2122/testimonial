import type { Auth, SpaceSummary } from '@/types/auth';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            currentSpace: SpaceSummary | null;
            flash?: { success?: string; error?: string };
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
