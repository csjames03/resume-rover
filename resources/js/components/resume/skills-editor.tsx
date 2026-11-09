import { router, useForm } from '@inertiajs/react';
import { Plus, X } from 'lucide-react';
import ResumeSkillController from '@/actions/App/Http/Controllers/ResumeSkillController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { Skill } from '@/types';

/**
 * Skills drive matching, so they're editable chips: remove wrong ones, add missing ones.
 */
export function SkillsEditor({
    skills,
    suggestions,
}: {
    skills: Skill[];
    suggestions: string[] | undefined;
}) {
    const form = useForm({ name: '' });

    const add = (e: React.FormEvent) => {
        e.preventDefault();
        form.submit(ResumeSkillController.store(), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    const remove = (skill: Skill) =>
        router.delete(ResumeSkillController.destroy(skill.id).url, {
            preserveScroll: true,
        });

    return (
        <div className="space-y-4">
            {skills.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No skills detected yet. Add the ones you'd want matched.
                </p>
            ) : (
                <ul className="flex flex-wrap gap-2" aria-label="Your skills">
                    {skills.map((skill) => (
                        <li
                            key={skill.id}
                            className="inline-flex items-center gap-1 rounded-full border py-0.5 pr-1 pl-3 text-sm"
                        >
                            {skill.name}
                            <button
                                type="button"
                                onClick={() => remove(skill)}
                                className="rounded-full p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                                aria-label={`Remove ${skill.name}`}
                            >
                                <X className="size-3.5" />
                            </button>
                        </li>
                    ))}
                </ul>
            )}

            <form onSubmit={add} className="flex max-w-sm gap-2">
                <Input
                    value={form.data.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                    placeholder="Add a skill, e.g. Laravel"
                    list="skill-suggestions"
                    aria-label="Skill name"
                />
                <datalist id="skill-suggestions">
                    {suggestions?.map((name) => (
                        <option key={name} value={name} />
                    ))}
                </datalist>
                <Button
                    type="submit"
                    variant="outline"
                    disabled={form.processing || !form.data.name.trim()}
                >
                    <Plus /> Add
                </Button>
            </form>
            <InputError message={form.errors.name} />
        </div>
    );
}
