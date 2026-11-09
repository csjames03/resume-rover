import { Link, usePage } from '@inertiajs/react';
import { Check } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
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
            <div className="relative hidden flex-col justify-between overflow-hidden bg-primary p-10 text-primary-foreground lg:flex">
                <div
                    aria-hidden
                    className="absolute -right-32 -bottom-32 size-[480px] rounded-full bg-white/10 blur-3xl"
                />
                <AppLogoIcon
                    aria-hidden
                    className="absolute -top-20 -right-20 size-96 fill-current opacity-[0.07]"
                />

                <Link
                    href={home()}
                    className="relative flex items-center gap-2.5 text-lg font-bold tracking-tight"
                >
                    <span className="flex size-9 items-center justify-center rounded-lg bg-white/15">
                        <AppLogoIcon className="size-5 fill-current" />
                    </span>
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
                                className="flex items-center gap-3 text-primary-foreground/90"
                            >
                                <span className="flex size-6 items-center justify-center rounded-full bg-white/15">
                                    <Check className="size-3.5" aria-hidden />
                                </span>
                                {point}
                            </li>
                        ))}
                    </ul>
                </div>

                <p className="relative text-sm text-primary-foreground/70">
                    Official public job feeds only. Your data stays yours.
                </p>
            </div>

            <div className="flex items-center justify-center p-6 md:p-10">
                <div className="flex w-full max-w-sm flex-col gap-6">
                    <Link
                        href={home()}
                        className="flex items-center gap-2.5 font-bold tracking-tight lg:hidden"
                    >
                        <span className="flex size-9 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                            <AppLogoIcon className="size-5 fill-current" />
                        </span>
                        {name}
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
