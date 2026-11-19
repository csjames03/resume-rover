import { Link, usePage } from '@inertiajs/react';
import { Check } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import AppLogoLockup from '@/components/app-logo-lockup';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

const POINTS = [
    'Every job scored against your real résumé',
    'Strengths and gaps behind each score',
    'Honest tailored drafts, sent only when you say so',
];

export default function AuthSplitLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { name } = usePage().props;

    return (
        <div className="grid min-h-dvh lg:grid-cols-2">
            <div className="relative hidden flex-col justify-between overflow-hidden bg-brand-ink p-10 text-white lg:flex">
                <div
                    aria-hidden
                    className="absolute -right-40 -bottom-40 size-[520px] rounded-full bg-brand/25 blur-3xl"
                />
                <div
                    aria-hidden
                    className="absolute -top-24 -left-24 size-80 rounded-full bg-brand/10 blur-3xl"
                />

                <Link
                    href={home()}
                    className="relative flex items-center gap-3 text-lg font-bold tracking-tight"
                >
                    <AppLogoIcon className="size-10 rounded-xl ring-1 ring-white/10" />
                    {name}
                </Link>

                <div className="relative max-w-md space-y-6">
                    <p className="text-3xl leading-tight font-bold tracking-tight">
                        Spend your job search on the roles that fit.
                    </p>
                    <ul className="space-y-3">
                        {POINTS.map((point) => (
                            <li
                                key={point}
                                className="flex items-center gap-3 text-white/85"
                            >
                                <span className="flex size-6 shrink-0 items-center justify-center rounded-full bg-brand/20 text-brand">
                                    <Check className="size-3.5" aria-hidden />
                                </span>
                                {point}
                            </li>
                        ))}
                    </ul>
                </div>

                <p className="relative text-sm text-white/60">
                    Official public job feeds only. Your data stays yours.
                </p>
            </div>

            <div className="flex items-center justify-center p-6 md:p-10">
                <div className="flex w-full max-w-sm animate-enter flex-col gap-6">
                    <Link href={home()} className="w-fit lg:hidden">
                        <AppLogoLockup className="h-9 w-auto" />
                    </Link>
                    <div className="space-y-1.5">
                        <h1 className="text-2xl font-bold tracking-tight">
                            {title}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {description}
                        </p>
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}
