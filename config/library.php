<?php

// Manager Library settings, all from the environment (plan.md §2). No secret has a default value (Article IX).
return [
    'jwt_secret' => env('JWT_SECRET', ''),
    'admin_username' => env('ADMIN_USERNAME', ''),
    'admin_password' => env('ADMIN_PASSWORD', ''),
    'covers_dir' => env('COVERS_DIR', 'storage/app/covers'),
    'clock_offset_days' => (int) env('CLOCK_OFFSET_DAYS', 0), // demo seeding only (plan.md §4); 0 in production
];
