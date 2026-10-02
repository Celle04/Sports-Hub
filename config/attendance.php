<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Late Grace Period
    |--------------------------------------------------------------------------
    |
    | Number of minutes after an attendance session's start time during which
    | a check-in is still recorded as "Present". Anything later is "Late".
    | Individual sessions may override this with their own grace period.
    |
    */

    'late_grace_minutes' => (int) env('ATTENDANCE_LATE_GRACE_MINUTES', 10),

    /*
    |--------------------------------------------------------------------------
    | Attendance Session Statuses
    |--------------------------------------------------------------------------
    */

    'session_statuses' => ['Open', 'Closed', 'Cancelled'],

    /*
    |--------------------------------------------------------------------------
    | Attendance Record Statuses
    |--------------------------------------------------------------------------
    |
    | "Pending" is assigned automatically when a roster is generated and is
    | intentionally excluded from attendance rate calculations.
    |
    */

    'record_statuses' => ['Present', 'Late', 'Absent', 'Excused'],

    'counted_statuses' => ['Present', 'Late', 'Absent', 'Excused'],

];
