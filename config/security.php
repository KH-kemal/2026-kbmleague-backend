<?php

return [
    // Closed by default: public pages and existing-account login remain available.
    'public_registration' => (bool) env('PUBLIC_REGISTRATION_ENABLED', false),
];
