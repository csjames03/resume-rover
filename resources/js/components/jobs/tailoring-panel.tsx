import { Link, router } from '@inertiajs/react';
import { FileText, Wand2 } from 'lucide-react';
import TailoredResumeController from '@/actions/App/Http/Controllers/TailoredResumeController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { timeAgo } from '@/lib/format';
import type { TailoredResume } from '@/types';

const STATUS_LABEL: Record<TailoredResume['status'], string> = {
    generating: 'Generating',
    draft: 'Draft',
    approved: 'Approved',
    failed: 'Failed',
};

export function TailoringPanel({
    postingId,
    tailoredResumes,
    aiEnabled,
    canTailor,
}: {
    postingId: number;
    tailoredResumes: TailoredResume[];
    aiEnabled: boolean;
    canTailor: boolean;
}) {
    const generate = () =>
        router.post(TailoredResumeController.store(postingId).url);

    return (
        <Card>
            <CardHeader>
                <CardTitle>Tailored résumé</CardTitle>
                <CardDescription>
                    Reorders and rephrases your real experience for this role.
                    Nothing is invented, and every line links to its source.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
                {tailoredResumes.length > 0 && (
                    <ul className="space-y-1">
                        {tailoredResumes.map((tailored) => (
                            <li key={tailored.id}>
                                <Link
                                    href={TailoredResumeController.show(
                                        tailored.id,
                                    )}
                                    className="-mx-2 flex items-center justify-between gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-muted/50"
                                >
                                    <span className="flex items-center gap-2">
                                        {tailored.status === 'generating' ? (
                                            <Spinner />
                                        ) : (
                                            <FileText className="size-4 text-muted-foreground" />
                                        )}
                                        Version {tailored.version}
                                        <span className="text-muted-foreground">
                                            · {timeAgo(tailored.created_at)}
                                        </span>
                                    </span>
                                    <Badge
                                        variant={
                                            tailored.status === 'approved'
                                                ? 'default'
                                                : 'secondary'
                                        }
                                    >
                                        {STATUS_LABEL[tailored.status]}
                                    </Badge>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}

                {aiEnabled ? (
                    <Button
                        variant="outline"
                        onClick={generate}
                        disabled={!canTailor}
                    >
                        <Wand2 />{' '}
                        {tailoredResumes.length > 0
                            ? 'Generate another version'
                            : 'Generate draft'}
                    </Button>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        Needs an AI provider. Add a Gemini, Groq or OpenRouter
                        key to your .env file.
                    </p>
                )}
            </CardContent>
        </Card>
    );
}
