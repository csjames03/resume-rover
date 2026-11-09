import { Head, useForm } from '@inertiajs/react';
import PreferencesController from '@/actions/App/Http/Controllers/Settings/PreferencesController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { OptionSelect } from '@/components/option-select';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/preferences';
import type { Option } from '@/types';

type Preferences = {
    target_titles: string[];
    locations: string[];
    remote_preference: string;
    seniority: string | null;
    excluded_keywords: string[];
};

type Props = {
    preferences: Preferences;
    remoteOptions: Option[];
    seniorityOptions: Option[];
};

// Lists are edited as comma-separated text and split on submit.
const toText = (values: string[]) => values.join(', ');
const toList = (text: string) =>
    text
        .split(',')
        .map((v) => v.trim())
        .filter(Boolean);

export default function PreferencesSettings({
    preferences,
    remoteOptions,
    seniorityOptions,
}: Props) {
    const form = useForm({
        target_titles: toText(preferences.target_titles),
        locations: toText(preferences.locations),
        remote_preference: preferences.remote_preference,
        seniority: preferences.seniority,
        excluded_keywords: toText(preferences.excluded_keywords),
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            target_titles: toList(data.target_titles),
            locations: toList(data.locations),
            excluded_keywords: toList(data.excluded_keywords),
        }));
        form.submit(PreferencesController.update(), { preserveScroll: true });
    };

    return (
        <>
            <Head title="Job preferences" />

            <h1 className="sr-only">Job preferences</h1>

            <form onSubmit={submit} className="space-y-6">
                <Heading
                    variant="small"
                    title="Job preferences"
                    description="Used by scoring: mismatches show up as gaps and lower the score."
                />

                <div className="grid gap-2">
                    <Label htmlFor="target_titles">Target job titles</Label>
                    <Input
                        id="target_titles"
                        value={form.data.target_titles}
                        onChange={(e) =>
                            form.setData('target_titles', e.target.value)
                        }
                        placeholder="Backend Developer, Laravel Developer"
                    />
                    <p className="text-xs text-muted-foreground">
                        Comma-separated. Matched against posting titles.
                    </p>
                    <InputError message={form.errors.target_titles} />
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="remote_preference">
                            Work arrangement
                        </Label>
                        <OptionSelect
                            id="remote_preference"
                            value={form.data.remote_preference}
                            options={remoteOptions}
                            onChange={(value) =>
                                form.setData(
                                    'remote_preference',
                                    value ?? 'any',
                                )
                            }
                            className="w-full"
                        />
                        <InputError message={form.errors.remote_preference} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="seniority">Target level</Label>
                        <OptionSelect
                            id="seniority"
                            value={form.data.seniority}
                            options={seniorityOptions}
                            anyLabel="Not set"
                            onChange={(value) =>
                                form.setData('seniority', value)
                            }
                            className="w-full"
                        />
                        <InputError message={form.errors.seniority} />
                    </div>
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="locations">Preferred locations</Label>
                    <Input
                        id="locations"
                        value={form.data.locations}
                        onChange={(e) =>
                            form.setData('locations', e.target.value)
                        }
                        placeholder="Manila, Singapore"
                    />
                    <p className="text-xs text-muted-foreground">
                        Leave empty to accept any location. Remote roles always
                        pass.
                    </p>
                    <InputError message={form.errors.locations} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="excluded_keywords">
                        Exclude roles containing
                    </Label>
                    <Input
                        id="excluded_keywords"
                        value={form.data.excluded_keywords}
                        onChange={(e) =>
                            form.setData('excluded_keywords', e.target.value)
                        }
                        placeholder="sales, clearance"
                    />
                    <p className="text-xs text-muted-foreground">
                        Matching titles are pushed to Low match.
                    </p>
                    <InputError message={form.errors.excluded_keywords} />
                </div>

                <Button type="submit" disabled={form.processing}>
                    Save preferences
                </Button>
            </form>
        </>
    );
}

PreferencesSettings.layout = {
    breadcrumbs: [{ title: 'Job preferences', href: edit() }],
};
