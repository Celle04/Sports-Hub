<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Clearance Expiration Window
    |--------------------------------------------------------------------------
    |
    | Number of days before a certificate or checkup deadline is flagged as
    | "Expiring Soon" in the summary card, alerts and expiration panel.
    |
    */

    'expiring_days' => (int) env('MEDICAL_EXPIRING_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Medical Statuses
    |--------------------------------------------------------------------------
    |
    | Cleared      - the athlete may participate normally.
    | Pending      - the medical evaluation is still incomplete.
    | Restricted   - the athlete may participate with restrictions.
    | Not Cleared  - the athlete should not participate.
    |
    */

    'statuses' => ['Pending', 'Cleared', 'Restricted', 'Not Cleared'],

    /*
    |--------------------------------------------------------------------------
    | Medical Certificate Uploads
    |--------------------------------------------------------------------------
    |
    | Certificates are stored on the private disk and are only ever served
    | through an authenticated route, never through a public URL.
    |
    */

    'certificate_mimes' => ['pdf', 'jpg', 'jpeg', 'png'],
    'certificate_max_kb' => 2048,

    /*
    |--------------------------------------------------------------------------
    | Injury / Medical Incident Tracking
    |--------------------------------------------------------------------------
    */

    'incident_severities' => ['Mild', 'Moderate', 'Severe'],
    'incident_clearances' => ['Pending', 'Cleared', 'Not Cleared'],

];
