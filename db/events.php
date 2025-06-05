<?php

// File: local/questionnaire_notify/db/events.php
defined('MOODLE_INTERNAL') || die();

return [
    [
        'eventname'   => '\mod_questionnaire\event\attempt_submitted',
        'callback'    => '\local_questionnaire_notify\observer::on_attempt_submitted',
        'priority'    => 9999,
        'internal'    => false,
    ],
];
