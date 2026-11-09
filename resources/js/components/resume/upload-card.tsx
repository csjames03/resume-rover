import { useForm } from '@inertiajs/react';
import { FileUp } from 'lucide-react';
import { useRef, useState } from 'react';
import ResumeController from '@/actions/App/Http/Controllers/ResumeController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';

/**
 * Drop zone + file picker. Uploading replaces the active résumé.
 */
export function UploadCard({ compact = false }: { compact?: boolean }) {
    const input = useRef<HTMLInputElement>(null);
    const [dragging, setDragging] = useState(false);
    const form = useForm<{ file: File | null }>({ file: null });

    const upload = (file: File | undefined) => {
        if (!file) {
            return;
        }

        form.transform(() => ({ file }));
        form.submit(ResumeController.store(), {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => {
                if (input.current) {
                    input.current.value = '';
                }
            },
        });
    };

    if (compact) {
        return (
            <>
                <input
                    ref={input}
                    type="file"
                    accept=".pdf,.docx"
                    className="sr-only"
                    onChange={(e) => upload(e.target.files?.[0])}
                />
                <Button
                    variant="outline"
                    size="sm"
                    disabled={form.processing}
                    onClick={() => input.current?.click()}
                >
                    {form.processing ? <Spinner /> : <FileUp />}
                    Replace file
                </Button>
                <InputError message={form.errors.file} />
            </>
        );
    }

    return (
        <div>
            <label
                onDragOver={(e) => {
                    e.preventDefault();
                    setDragging(true);
                }}
                onDragLeave={() => setDragging(false)}
                onDrop={(e) => {
                    e.preventDefault();
                    setDragging(false);
                    upload(e.dataTransfer.files[0]);
                }}
                className={cn(
                    'flex cursor-pointer flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed px-6 py-16 text-center transition-colors',
                    dragging ? 'border-primary bg-muted' : 'hover:bg-muted/50',
                )}
            >
                <input
                    ref={input}
                    type="file"
                    accept=".pdf,.docx"
                    className="sr-only"
                    onChange={(e) => upload(e.target.files?.[0])}
                />
                {form.processing ? (
                    <Spinner className="size-8" />
                ) : (
                    <FileUp
                        className="size-8 text-muted-foreground"
                        aria-hidden
                    />
                )}
                <div>
                    <p className="font-medium">
                        {form.processing
                            ? 'Uploading…'
                            : 'Drop your résumé here, or click to choose'}
                    </p>
                    <p className="mt-1 text-sm text-muted-foreground">
                        PDF or Word (.docx), up to 5 MB. The original file is
                        kept private and unchanged.
                    </p>
                </div>
            </label>
            <InputError className="mt-2" message={form.errors.file} />
        </div>
    );
}
