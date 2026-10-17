<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

        /*
        | Customer statements. Files are encrypted by the app before they are
        | written, so either disk only ever holds ciphertext. Use the local
        | disk in development and the S3-compatible one in production; it
        | works with AWS S3, OCI Object Storage (S3 compatibility API) and
        | Google Cloud Storage (interoperability mode) — set the endpoint and
        | region of the provider's India region.
        */
        'statements' => [
            'driver' => 'local',
            'root' => storage_path('app/private/statements'),
            'visibility' => 'private',
            'throw' => true,
        ],

        'statements_s3' => [
            'driver' => 's3',
            'key' => env('STATEMENTS_S3_KEY'),
            'secret' => env('STATEMENTS_S3_SECRET'),
            'region' => env('STATEMENTS_S3_REGION', 'ap-south-1'),
            'bucket' => env('STATEMENTS_S3_BUCKET'),
            'endpoint' => env('STATEMENTS_S3_ENDPOINT'),
            'use_path_style_endpoint' => env('STATEMENTS_S3_PATH_STYLE', false),
            'root' => 'statements',
            'visibility' => 'private',
            // Provider-side encryption on top of the app's own encryption
            'options' => ['ServerSideEncryption' => 'AES256'],
            'throw' => true,
        ],

        /*
        | Database backups (spatie/laravel-backup). Archives are password
        | encrypted; keep the production copy in a different account/region
        | from the database.
        */
        'backups' => [
            'driver' => 'local',
            'root' => storage_path('app/private/backups'),
            'throw' => true,
        ],

        'backups_s3' => [
            'driver' => 's3',
            'key' => env('BACKUP_S3_KEY'),
            'secret' => env('BACKUP_S3_SECRET'),
            'region' => env('BACKUP_S3_REGION', 'ap-south-1'),
            'bucket' => env('BACKUP_S3_BUCKET'),
            'endpoint' => env('BACKUP_S3_ENDPOINT'),
            'use_path_style_endpoint' => env('BACKUP_S3_PATH_STYLE', false),
            'root' => 'backups',
            'visibility' => 'private',
            'options' => ['ServerSideEncryption' => 'AES256'],
            'throw' => true,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
