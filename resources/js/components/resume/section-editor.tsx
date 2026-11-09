import { router, useForm } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';
import ResumeSectionController from '@/actions/App/Http/Controllers/ResumeSectionController';
import InputError from '@/components/input-error';
import { OptionSelect } from '@/components/option-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { formatDateRange } from '@/lib/format';
import type { Option, ResumeSection } from '@/types';

type SectionForm = {
    type: string;
    title: string;
    organization: string;
    location: string;
    start_date: string;
    end_date: string;
    is_current: boolean;
    description: string;
    bullets: string[];
};

const LOW_CONFIDENCE = 0.6;

/**
 * One parsed entry, shown read-only until the user chooses to correct it.
 */
export function SectionCard({
    section,
    sectionTypes,
}: {
    section: ResumeSection;
    sectionTypes: Option[];
}) {
    const [editing, setEditing] = useState(false);

    if (editing) {
        return (
            <SectionForm
                section={section}
                sectionTypes={sectionTypes}
                onDone={() => setEditing(false)}
            />
        );
    }

    const dates = formatDateRange(
        section.start_date,
        section.end_date,
        section.is_current,
    );
    const needsReview =
        !section.is_user_edited &&
        section.confidence !== null &&
        section.confidence < LOW_CONFIDENCE;

    return (
        <article className="group rounded-lg border p-4">
            <div className="flex items-start justify-between gap-4">
                <div className="min-w-0 space-y-0.5">
                    <h4 className="font-medium">
                        {section.title ?? (
                            <span className="text-muted-foreground italic">
                                Untitled
                            </span>
                        )}
                    </h4>
                    <p className="text-sm text-muted-foreground">
                        {[section.organization, section.location, dates]
                            .filter(Boolean)
                            .join(' · ')}
                    </p>
                </div>
                <div className="flex shrink-0 items-center gap-1">
                    {needsReview && <Badge variant="outline">Check this</Badge>}
                    <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => setEditing(true)}
                        aria-label="Edit section"
                    >
                        <Pencil />
                    </Button>
                </div>
            </div>
            {section.description && (
                <p className="mt-2 text-sm whitespace-pre-line">
                    {section.description}
                </p>
            )}
            {section.bullets.length > 0 && (
                <ul className="mt-2 list-disc space-y-1 pl-5 text-sm">
                    {section.bullets.map((bullet) => (
                        <li key={bullet.id}>{bullet.text}</li>
                    ))}
                </ul>
            )}
        </article>
    );
}

/**
 * Create (no section) or edit (with section) form. Bullets are edited one per line.
 */
export function SectionForm({
    section,
    sectionTypes,
    defaultType = 'experience',
    onDone,
}: {
    section?: ResumeSection;
    sectionTypes: Option[];
    defaultType?: string;
    onDone: () => void;
}) {
    const form = useForm<SectionForm>({
        type: section?.type ?? defaultType,
        title: section?.title ?? '',
        organization: section?.organization ?? '',
        location: section?.location ?? '',
        start_date: section?.start_date ?? '',
        end_date: section?.end_date ?? '',
        is_current: section?.is_current ?? false,
        description: section?.description ?? '',
        bullets: section?.bullets.map((b) => b.text) ?? [],
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            start_date: data.start_date || null,
            end_date: data.end_date || null,
        }));
        form.submit(
            section
                ? ResumeSectionController.update(section.id)
                : ResumeSectionController.store(),
            { preserveScroll: true, onSuccess: onDone },
        );
    };

    const remove = () => {
        if (section && confirm('Remove this section from your profile?')) {
            router.delete(ResumeSectionController.destroy(section.id).url, {
                preserveScroll: true,
            });
        }
    };

    const id = (field: string) => `section-${section?.id ?? 'new'}-${field}`;

    return (
        <form
            onSubmit={submit}
            className="space-y-4 rounded-lg border bg-muted/30 p-4"
        >
            <div className="grid gap-4 sm:grid-cols-2">
                <Field
                    label="Type"
                    htmlFor={id('type')}
                    error={form.errors.type}
                >
                    <OptionSelect
                        id={id('type')}
                        value={form.data.type}
                        options={sectionTypes}
                        onChange={(value) =>
                            form.setData('type', value ?? 'other')
                        }
                        className="w-full"
                    />
                </Field>
                <Field
                    label="Title"
                    htmlFor={id('title')}
                    error={form.errors.title}
                >
                    <Input
                        id={id('title')}
                        value={form.data.title}
                        onChange={(e) => form.setData('title', e.target.value)}
                        placeholder="Software Engineer"
                    />
                </Field>
                <Field
                    label="Organization"
                    htmlFor={id('organization')}
                    error={form.errors.organization}
                >
                    <Input
                        id={id('organization')}
                        value={form.data.organization}
                        onChange={(e) =>
                            form.setData('organization', e.target.value)
                        }
                        placeholder="Company or school"
                    />
                </Field>
                <Field
                    label="Location"
                    htmlFor={id('location')}
                    error={form.errors.location}
                >
                    <Input
                        id={id('location')}
                        value={form.data.location}
                        onChange={(e) =>
                            form.setData('location', e.target.value)
                        }
                    />
                </Field>
                <Field
                    label="Start"
                    htmlFor={id('start')}
                    error={form.errors.start_date}
                >
                    <Input
                        id={id('start')}
                        type="date"
                        value={form.data.start_date}
                        onChange={(e) =>
                            form.setData('start_date', e.target.value)
                        }
                    />
                </Field>
                <Field
                    label="End"
                    htmlFor={id('end')}
                    error={form.errors.end_date}
                >
                    <Input
                        id={id('end')}
                        type="date"
                        value={form.data.end_date}
                        disabled={form.data.is_current}
                        onChange={(e) =>
                            form.setData('end_date', e.target.value)
                        }
                    />
                </Field>
            </div>

            <div className="flex items-center gap-2">
                <Checkbox
                    id={id('current')}
                    checked={form.data.is_current}
                    onCheckedChange={(checked) =>
                        form.setData('is_current', checked === true)
                    }
                />
                <Label htmlFor={id('current')}>I currently do this</Label>
            </div>

            <Field
                label="Description"
                htmlFor={id('description')}
                error={form.errors.description}
            >
                <Textarea
                    id={id('description')}
                    value={form.data.description}
                    onChange={(e) =>
                        form.setData('description', e.target.value)
                    }
                    rows={2}
                />
            </Field>

            <Field
                label="Bullet points (one per line)"
                htmlFor={id('bullets')}
                error={form.errors.bullets}
            >
                <Textarea
                    id={id('bullets')}
                    value={form.data.bullets.join('\n')}
                    onChange={(e) =>
                        form.setData('bullets', e.target.value.split('\n'))
                    }
                    rows={Math.max(4, form.data.bullets.length + 1)}
                />
            </Field>

            <div className="flex items-center justify-between gap-2">
                <div className="flex gap-2">
                    <Button type="submit" disabled={form.processing}>
                        Save
                    </Button>
                    <Button type="button" variant="ghost" onClick={onDone}>
                        Cancel
                    </Button>
                </div>
                {section && (
                    <Button
                        type="button"
                        variant="ghost"
                        className="text-destructive"
                        onClick={remove}
                    >
                        <Trash2 /> Remove
                    </Button>
                )}
            </div>
        </form>
    );
}

function Field({
    label,
    htmlFor,
    error,
    children,
}: {
    label: string;
    htmlFor: string;
    error?: string;
    children: React.ReactNode;
}) {
    return (
        <div className="grid gap-1.5">
            <Label htmlFor={htmlFor}>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}
