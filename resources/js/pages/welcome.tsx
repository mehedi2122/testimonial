import { Head, Link, usePage } from '@inertiajs/react';

export default function Welcome() {
    const { auth, name } = usePage().props;

    return (
        <>
            <Head title="Welcome" />
            <div className="flex min-h-screen flex-col items-center bg-[#FDFDFC] p-6 text-[#1b1b18] lg:p-8 dark:bg-[#0a0a0a]">
                <header className="mb-6 w-full max-w-[335px] text-sm not-has-[nav]:hidden lg:max-w-4xl">
                    <nav className="flex items-center justify-end gap-4">
                        {auth.user ? (
                            <Link
                                href="/spaces"
                                className="inline-block rounded-sm border border-[#19140035] px-5 py-1.5 text-sm leading-normal text-[#1b1b18] hover:border-[#1915014a] dark:border-[#3E3E3A] dark:text-[#EDEDEC] dark:hover:border-[#62605b]"
                            >
                                Open Spaces
                            </Link>
                        ) : (
                            <>
                                <Link
                                    href="/login"
                                    className="inline-block rounded-sm border border-transparent px-5 py-1.5 text-sm leading-normal text-[#1b1b18] hover:border-[#19140035] dark:text-[#EDEDEC] dark:hover:border-[#3E3E3A]"
                                >
                                    Log in
                                </Link>
                                <Link
                                    href="/register"
                                    className="inline-block rounded-sm border border-[#19140035] px-5 py-1.5 text-sm leading-normal text-[#1b1b18] hover:border-[#1915014a] dark:border-[#3E3E3A] dark:text-[#EDEDEC] dark:hover:border-[#62605b]"
                                >
                                    Register
                                </Link>
                            </>
                        )}
                    </nav>
                </header>
                <div className="flex w-full items-center justify-center lg:grow">
                    <main className="flex w-full max-w-[335px] flex-col gap-6 lg:max-w-2xl">
                        <h1 className="text-3xl font-semibold tracking-tight">
                            {name}
                        </h1>
                        <p className="text-[15px] leading-relaxed text-[#706f6c] dark:text-[#A1A09A]">
                            Collect testimonials from your customers with a
                            public embed. Each testimonial flows through a
                            privacy-aware inbox, and the wall-of-love widget
                            drops into any site with a single script tag.
                        </p>
                        {!auth.user && (
                            <ul className="flex gap-3 text-sm leading-normal">
                                <li>
                                    <Link
                                        href="/register"
                                        className="inline-block rounded-sm border border-black bg-[#1b1b18] px-5 py-1.5 text-sm leading-normal text-white hover:border-black hover:bg-black dark:border-[#eeeeec] dark:bg-[#eeeeec] dark:text-[#1C1C1A] dark:hover:border-white dark:hover:bg-white"
                                    >
                                        Create your account
                                    </Link>
                                </li>
                            </ul>
                        )}
                    </main>
                </div>
            </div>
        </>
    );
}
