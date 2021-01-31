<?php

return [

    'quota' => [
        'api_authorization' => array_values(array_filter(array_map(
            'intval',
            explode(',', env('API_AUTHORIZATION_NOTIFICATION_QUOTA', '80,100')),
        ))),
        'subscription' => array_values(array_filter(array_map(
            'intval',
            explode(',', env('SUBSCRIPTION_NOTIFICATION_QUOTA', '80,100')),
        ))),
    ],

];
