<?php

/*
|--------------------------------------------------------------------------
| Job-fit scoring
|--------------------------------------------------------------------------
|
| Scores are computed in plain PHP from extracted requirements, so they are
| reproducible and cost nothing to recompute. Change "version" whenever the
| rules or weights change, so old and new scores can be told apart.
|
*/

return [

    'version' => 'rules-v2',

    // Score bands per the plan. Starting defaults, not objective truth: tune them on real matches.
    'bands' => [
        'strong' => 80,
        'relevant' => 55,
    ],

    // Points per component (sum = 100). Empty components hand their weight to the others.
    'weights' => [
        'must_have' => 60,
        'nice_to_have' => 15,
        'experience' => 15,
        'title' => 10,
    ],

    'penalties' => [
        'remote_mismatch' => 15,
        'seniority_mismatch' => 20,
        'location_mismatch' => 10,
    ],

    'senior_title_keywords' => ['senior', 'sr.', 'sr ', 'staff', 'principal', 'lead', 'head of', 'director', 'manager', 'architect'],

];
