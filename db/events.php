<?php

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\mod_questionnaire\event\response_submitted',
        'callback'  => '\local_questionnaire_notify\observer::on_response_submitted',
    ],
];

