import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Option } from '@/types';

// Radix Select can't use "" as a value, so "any" stands in for "no filter".
const ANY = '__any__';

/**
 * A select built from {value, label} options. Pass `anyLabel` to add a
 * "no selection" entry that maps to null.
 */
export function OptionSelect({
    id,
    value,
    options,
    onChange,
    anyLabel,
    placeholder,
    className,
}: {
    id?: string;
    value: string | null | undefined;
    options: Option[];
    onChange: (value: string | null) => void;
    anyLabel?: string;
    placeholder?: string;
    className?: string;
}) {
    // Render the label ourselves: Radix only fills it in after the menu has
    // mounted, so on first paint (and with SSR) the trigger could show up empty.
    const label =
        options.find((option) => option.value === value)?.label ??
        (value == null ? anyLabel : undefined);

    return (
        <Select
            value={value ?? (anyLabel ? ANY : undefined)}
            onValueChange={(next) => onChange(next === ANY ? null : next)}
        >
            <SelectTrigger id={id} className={className}>
                <SelectValue placeholder={placeholder}>{label}</SelectValue>
            </SelectTrigger>
            <SelectContent>
                {anyLabel && <SelectItem value={ANY}>{anyLabel}</SelectItem>}
                {options.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                        {option.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
