<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Certificate details
    |--------------------------------------------------------------------------
    |
    | The school information printed on every SportsHub certificate. These
    | values are configuration, not data: a school can adjust them without
    | touching the certificate template or the controllers.
    |
    */

    'school' => env('CERTIFICATE_SCHOOL', 'SURIGAO DEL NORTE NATIONAL HIGH SCHOOL'),

    'program' => env('CERTIFICATE_PROGRAM', 'SPORTSHUB'),

    'committee' => env('CERTIFICATE_COMMITTEE', 'SPORTS ACTIVITY COMMITTEE'),

    'heading' => env('CERTIFICATE_HEADING', 'CERTIFICATE OF ACHIEVEMENT'),

    'presented_to' => env('CERTIFICATE_PRESENTED_TO', 'This certificate is proudly presented to'),

    'footer' => env('CERTIFICATE_FOOTER', 'SNNHS SPORTSHUB · SURIGAO DEL NORTE NATIONAL HIGH SCHOOL'),

    /*
    |--------------------------------------------------------------------------
    | Certificate reference
    |--------------------------------------------------------------------------
    |
    | Every issued certificate carries a reference built from this prefix, the
    | year it was issued and the achievement number, for example
    | "SNNHS-ACH-2026-0001". The prefix and the year are configurable so a
    | school can follow its own numbering scheme.
    |
    */

    'reference_prefix' => env('CERTIFICATE_REFERENCE_PREFIX', 'SNNHS-ACH'),

    /*
    |--------------------------------------------------------------------------
    | Signatories
    |--------------------------------------------------------------------------
    |
    | The Sports Coordinator signature belongs to the administrator who
    | generated the certificate, so that name is always taken from the
    | session. The second signature is a school official and stays unnamed
    | until the school authorizes a name, because a certificate must never
    | carry a name or a seal that has not been approved.
    |
    */

    'signatories' => [
        'coordinator' => [
            'title' => env('CERTIFICATE_COORDINATOR_TITLE', 'Sports Coordinator'),
        ],
        'official' => [
            'name' => env('CERTIFICATE_OFFICIAL_NAME'),
            'title' => env('CERTIFICATE_OFFICIAL_TITLE', 'School Principal'),
        ],
    ],

];
