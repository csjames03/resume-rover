// Mirrors app/Http/Resources/*. Keep the two in sync.

export type Option = { value: string; label: string };

export type ParseStatus = 'pending' | 'processing' | 'parsed' | 'failed';
export type MatchCategory = 'strong' | 'relevant' | 'low';
export type ApplicationStatus =
    | 'saved'
    | 'preparing'
    | 'applied'
    | 'interviewing'
    | 'offer'
    | 'rejected'
    | 'withdrawn';

export type Skill = {
    id: number;
    name: string;
    category: string;
    source?: 'parsed' | 'manual';
};

export type ResumeSection = {
    id: number;
    type: string;
    title: string | null;
    organization: string | null;
    location: string | null;
    start_date: string | null;
    end_date: string | null;
    is_current: boolean;
    description: string | null;
    confidence: number | null;
    is_user_edited: boolean;
    bullets: { id: number; text: string }[];
};

export type Resume = {
    id: number;
    original_filename: string;
    file_size: number;
    parse_status: ParseStatus;
    parse_error: string | null;
    parser: string | null;
    parsed_at: string | null;
    profile_version: number;
    uploaded_at: string;
    sections?: ResumeSection[];
    skills?: Skill[];
};

export type MatchPoint = {
    type: string;
    label: string;
    detail?: string;
    kind?: 'must_have' | 'nice_to_have';
};

export type JobMatch = {
    score: number;
    category: MatchCategory;
    category_label: string;
    strengths: MatchPoint[];
    gaps: MatchPoint[];
    scored_at: string;
    is_stale: boolean;
    explanation: string | null;
    explanation_status: 'pending' | 'ready' | 'failed' | null;
};

export type ApplicationEvent = {
    id: number;
    type: string;
    label: string;
    from_status: string | null;
    to_status: string | null;
    created_at: string;
};

export type Application = {
    id: number;
    status: ApplicationStatus;
    status_label: string;
    applied_at: string | null;
    next_action: string | null;
    next_action_at: string | null;
    notes: string | null;
    tailored_resume_id: number | null;
    archived_at: string | null;
    updated_at: string;
    posting?: {
        id: number;
        title: string;
        company: string;
        closed_at: string | null;
    };
    events?: ApplicationEvent[];
};

export type JobPosting = {
    id: number;
    title: string;
    company: string;
    location: string | null;
    remote_type: string;
    remote_label: string;
    employment_type: string | null;
    salary_text: string | null;
    url: string;
    posted_at: string | null;
    first_seen_at: string;
    closed_at: string | null;
    source?: { name: string; driver: string; attribution: string | null };
    match: JobMatch | null;
    application: Application | null;
    description?: string;
};

export type TailoredBullet = {
    text: string;
    source_bullet_ids: number[];
    sources: string[];
    flags: string[];
};

export type TailoredSection = {
    section_id: number;
    type: string;
    title: string | null;
    organization: string | null;
    dates: string | null;
    bullets: TailoredBullet[];
};

export type TailoredResume = {
    id: number;
    version: number;
    status: 'generating' | 'draft' | 'approved' | 'failed';
    content: {
        summary: string | null;
        summary_flags: string[];
        sections: TailoredSection[];
        skills: string[];
    } | null;
    flagged_claims: { text: string; reason: string }[];
    provider: string | null;
    error: string | null;
    approved_at: string | null;
    created_at: string;
    posting?: { id: number; title: string; company: string };
};

export type JobSource = {
    id: number;
    name: string;
    driver: string;
    driver_label: string;
    identifier: string | null;
    title_filter: string | null;
    is_enabled: boolean;
    fetch_interval_minutes: number;
    effective_interval_minutes: number;
    last_fetched_at: string | null;
    last_status: 'ok' | 'failed' | null;
    last_error: string | null;
    last_fetched_count: number | null;
    open_postings_count?: number;
    compliance_notes: string | null;
};

export type Paginated<T> = {
    data: T[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
};

export type ApplicationEmail = {
    id: number;
    to: string | null;
    subject: string;
    body: string;
    attach_resume: boolean;
    flags: string[];
    status: 'draft' | 'sent' | 'failed';
    drafted_by: string | null;
    error: string | null;
    sent_at: string | null;
    created_at: string;
    resume_filename?: string | null;
    posting?: { id: number; title: string; company: string; url: string };
};

/** Mirrors App\Services\Ai\AiAvailability::status(). */
export type AiUsage = {
    enabled: boolean;
    remaining: number;
    limit: number;
    resets_in: number;
    busy_for: number;
};
