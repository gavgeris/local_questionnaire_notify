<?php
// File: lang/en/local_questionnaire_notify.php

$string['pluginname'] = 'Questionnaire Submission Notifier';
$string['emailsubject'] = 'Confirmation of your questionnaire submission in course: {$a}';
$string['emailbody'] = 'Dear {$a->firstname} {$a->lastname},

Thank you for submitting the questionnaire "{$a->questionnaire_name}" for the course: "{$a->coursename}".

SUBMISSION DETAILS:
Submitted on: {$a->submission_date}

YOUR RESPONSES 2:
{$a->responses}

We have received your submission and will contact you shortly.

Best regards,
Course Administration Team';