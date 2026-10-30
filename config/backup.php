<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Backup Storage
    |--------------------------------------------------------------------------
    |
    | Backups are compressed JSON snapshots of staff, customers, transactions
    | and settings. Point BACKUP_DISK at "s3" (AWS S3, Cloudflare R2,
    | DigitalOcean Spaces, ...) to keep them off the app server.
    |
    */

    'disk' => env('BACKUP_DISK', 'local'),

    'path' => env('BACKUP_PATH', 'backups'),

    // Number of most recent backups to keep; older ones are deleted.
    'keep' => (int) env('BACKUP_KEEP', 30),

];
