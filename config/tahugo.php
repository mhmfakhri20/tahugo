<?php

/*
|--------------------------------------------------------------------------
| Tahu Go application settings
|--------------------------------------------------------------------------
*/

return [

    /*
     * AUTH-6: automatic logout after inactivity, in minutes, per role.
     * Outlet staff get a long timeout so the cashier is not signed out in the middle of a shift.
     * SESSION_LIFETIME must be at least the longest value below.
     */
    'idle_timeout' => [
        'owner' => (int) env('IDLE_TIMEOUT_OWNER', 60),
        'warehouse_admin' => (int) env('IDLE_TIMEOUT_WAREHOUSE', 60),
        'outlet_staff' => (int) env('IDLE_TIMEOUT_OUTLET', 600),
    ],

];
