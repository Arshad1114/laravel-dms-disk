<?php

return [

    /*
     | The base URL of your DMS service.
     | Example: https://dms.internal or http://dms-service:8000
     */
    'url' => env('DMS_URL', ''),

    /*
     | The API token used to authenticate with the DMS service.
     | This is sent as: Authorization: Bearer <token>
     */
    'token' => env('DMS_TOKEN', ''),

    /*
     | The disk name on the DMS server to store files on.
     | The DMS server must have this disk configured and allowed.
     */
    'disk' => env('DMS_DISK', null),

    /*
     | HTTP request timeout in seconds.
     */
    'timeout' => env('DMS_TIMEOUT', 30),

    /*
     | How many times to retry a failed request before throwing.
     | Uses exponential backoff between attempts.
     */
    'retry' => env('DMS_RETRY', 3),

    /*
     | Milliseconds to wait between retry attempts.
     */
    'retry_delay' => env('DMS_RETRY_DELAY', 200),

];