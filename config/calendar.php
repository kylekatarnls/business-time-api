<?php return [
    'google_calendar_api_key' => env('GOOGLE_CALENDAR_API_KEY'),
    'google_calendar_api_url' => 'https://www.googleapis.com/calendar/v3/calendars/{{calendar}}%23holiday%40group.v.calendar.google.com/events',
    'office_url' => 'https://www.officeholidays.com/ics-clean/{{region}}',
];
