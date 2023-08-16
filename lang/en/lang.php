<?php return [
    'plugin' => [
        'name' => 'Survey',
        'description' => 'A simple survey plugin',
    ],
    'survey' => [
        'action' => 'Actions',
        'update' => 'Edit',
        'created_at' => 'Created at',
        'updated_at' => 'Updated at',
        'deleted_at' => 'Deleted at',
        'title' => 'Survey Title',
        'description' => 'Survey Description',
        'is_active' => 'Responses allowed',
        'is_anonym' => 'Is anonym survey?',
        'is_multiselect' => 'Multiple options allowed?',
        'options' => 'Survey Options',
        'notification_to' => 'Email address for notifications',
        'meta' => 'Surveys meta data',
        'statistics' => 'Statistics',
        'no_statistics' => 'No statistics available',
        'statistics_event_count' => 'Responses',
        'statistics_choices_count' => 'Choices',
        'tab_general' => 'General',
        'tab_options' => 'Options',
        'option_title' => 'Title',
        'option_text' => 'Text',
        'option_text_hint' => 'This text will be displayed as option including formatting.',
    ],
    'admin' => [
        'surveys' => 'Surveys',
        'survey_events' => 'Participations',
        'survey_choices' => 'Selected options',
    ],
    'mail' => [
        'admin_notification' => [
            'title' => 'Survey participation',
            'description' => 'Notification after a survey participation',
            'text' => 'Hello,<br><br>A new survey participation has been registered.',
            'user_name' => 'Name',
            'user_email' => 'Email',
            'user_phone' => 'Phone',
            'user_comment' => 'Comment',
            'submit_duration' => 'Page duration (in seconds)',
            'selected_choices' => 'Selected answers',
        ]
    ],
    'component' => [
        'name' => 'Survey',
        'description' => 'Add a survey to a page',
        'submit' => 'Submit',
        'total_choices' => 'Current evaluation',
        'error_invalid_request' => 'Invalid request',
        'error_already_submitted' => 'You already did participate in the survey.',
        'feedback_success' => 'Thank you for your participation!',
        'user_details' => 'Personal details',
        'user_name' => 'Your name',
        'user_email' => 'Your email adddress',
        'user_phone' => 'Your phone number',
        'user_comment' => 'Your comment',
        'user_privacy' => 'Privacy terms accepted',
        'user_privacy_notice' => 'You accept our <a href="/privacy">privacy terms</a> and agree on saving your data for contacting you regarding this survey.',
    ],
    'permissions' => [
        'survey_manage' => 'Manage surveys?',
    ],
];
