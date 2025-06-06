<?php
// File: local/questionnaire_notify/classes/observer.php
namespace local_questionnaire_notify;

use mod_questionnaire\event\attempt_submitted;

class observer {

    public static function on_attempt_submitted(attempt_submitted $event): void {
        global $DB;

        error_log('[questionnaire_notify] Event triggered - attempt_submitted');

        try {
            $userid = $event->userid;

            // Get the course module ID from the event context
            $context = $event->get_context();
            if ($context->contextlevel !== CONTEXT_MODULE) {
                error_log('[questionnaire_notify] Invalid context level: ' . $context->contextlevel);
                return;
            }

            $cmid = $context->instanceid;
            error_log("[questionnaire_notify] CM ID: $cmid, User ID: $userid");

            // Get course module and validate it's a questionnaire
            $cm = get_coursemodule_from_id('questionnaire', $cmid, 0, false, MUST_EXIST);
            $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
            $questionnaire = $DB->get_record('questionnaire', ['id' => $cm->instance], '*', MUST_EXIST);

            // Check if email notifications are enabled
            $sendemail = self::should_send_email($questionnaire->id);

            if (!$sendemail) {
                error_log("[questionnaire_notify] Email sending is disabled for questionnaire ID: {$questionnaire->id}");
                return;
            }

            // Get user details
            $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);

            // Get questionnaire responses
            $responses = self::get_questionnaire_responses($questionnaire->id, $userid);

            // Get custom email from questionnaire response (if exists)
            $custom_email = self::get_custom_email_from_responses($responses);

            // Prepare email data
            $emaildata = [
                'firstname' => $user->firstname,
                'lastname' => $user->lastname,
                'coursename' => $course->fullname,
                'questionnaire_name' => $questionnaire->name,
                'responses' => $responses['formatted'],
                'submission_date' => date('Y-m-d H:i:s')
            ];

            $subject = get_string('emailsubject', 'local_questionnaire_notify', $course->fullname);
            $body = get_string('emailbody', 'local_questionnaire_notify', $emaildata);

            // Send email to user (registered email)
            if (email_to_user($user, \core_user::get_support_user(), $subject, $body)) {
                error_log("[questionnaire_notify] Email sent successfully to user: {$user->email}");
            } else {
                error_log("[questionnaire_notify] Failed to send email to user: {$user->email}");
            }

            // Send email to custom email if provided
            if ($custom_email && filter_var($custom_email, FILTER_VALIDATE_EMAIL)) {
                $custom_user = new \stdClass();
                $custom_user->email = $custom_email;
                $custom_user->firstname = 'Recipient';
                $custom_user->lastname = '';
                $custom_user->id = -1;
                $custom_user->deleted = 0;
                $custom_user->suspended = 0;
                $custom_user->auth = 'manual';
                $custom_user->confirmed = 1;
                $custom_user->mailformat = 1;

                if (email_to_user($custom_user, \core_user::get_support_user(), $subject, $body)) {
                    error_log("[questionnaire_notify] Email sent successfully to custom email: {$custom_email}");
                } else {
                    error_log("[questionnaire_notify] Failed to send email to custom email: {$custom_email}");
                }
            }

        } catch (\Throwable $e) {
            error_log('[questionnaire_notify] Exception: ' . $e->getMessage());
            error_log('[questionnaire_notify] Stack trace: ' . $e->getTraceAsString());
        }
    }

    /**
     * Get questionnaire responses for a specific user
     */
    private static function get_questionnaire_responses($questionnaireid, $userid): array {
        global $DB;

        $responses = [];
        $formatted_responses = '';

        try {
            // Get the latest response for this user and questionnaire
            $response_record = $DB->get_record_sql(
                "SELECT id, submitted 
                 FROM {questionnaire_response} 
                 WHERE questionnaireid = ? AND userid = ? 
                 ORDER BY submitted DESC 
                 LIMIT 1",
                [$questionnaireid, $userid]
            );

            if (!$response_record) {
                return ['raw' => [], 'formatted' => 'No responses found.'];
            }

            // Get all question responses for this submission
            $question_responses = $DB->get_records_sql(
                "SELECT qr.id, qr.question_id, qc.response as text_response,
                        q.name as question_name, q.type_id as question_type,
                        qc.content as choice_content
                 FROM {questionnaire_resp_single} qr
                 JOIN {questionnaire_question} q ON q.id = qr.question_id
                 LEFT JOIN {questionnaire_quest_choice} qc ON qc.id = qr.choice_id
                 WHERE qr.response_id = ?
                 ORDER BY q.position",
                [$response_record->id]
            );

            // Also get text responses
            $text_responses = $DB->get_records_sql(
                "SELECT qr.id, qr.question_id, qr.response as text_response,
                        q.name as question_name, q.type_id as question_type
                    FROM {questionnaire_response_text} qr
         JOIN {questionnaire_question} q ON q.id = qr.question_id
         LEFT JOIN {questionnaire_response_text} qrt ON qrt.id = qr.response_id
                 WHERE qr.response_id = ?
                 ORDER BY q.position",
                [$response_record->id]
            );

            // Combine responses
            $all_responses = array_merge($question_responses, $text_responses);

            foreach ($all_responses as $resp) {
                $question_name = clean_text($resp->question_name);
                $answer = '';

                if (!empty($resp->text_response)) {
                    $answer = clean_text($resp->text_response);
                }

                $responses[$resp->question_id] = [
                    'question' => $question_name,
                    'answer' => $answer,
                    'type' => $resp->question_type
                ];

                $formatted_responses .= "Q: {$question_name}\nA: {$answer}\n\n";
            }

        } catch (\Exception $e) {
            error_log('[questionnaire_notify] Error getting responses: ' . $e->getMessage());
            $formatted_responses = 'Error retrieving questionnaire responses.';
        }

        return [
            'raw' => $responses,
            'formatted' => $formatted_responses
        ];
    }

    /**
     * Extract custom email from questionnaire responses
     * Looks for a question that might contain an email address
     */
    private static function get_custom_email_from_responses($responses): ?string {
        if (!isset($responses['raw']) || empty($responses['raw'])) {
            return null;
        }

        foreach ($responses['raw'] as $response) {
            $question = strtolower($response['question']);
            $answer = $response['answer'];

            // Check if question contains email-related keywords
            if ((strpos($question, 'email') !== false ||
                    strpos($question, 'e-mail') !== false ||
                    strpos($question, 'Email') !== false ) &&
                filter_var($answer, FILTER_VALIDATE_EMAIL)) {
                return $answer;
            }
        }

        return null;
    }

    /**
     * Check if email should be sent for this questionnaire
     */
    private static function should_send_email($questionnaireid): bool {
        try {
            $handler = \core_customfield\handler::get_handler('mod_questionnaire', 'mod_questionnaire');
            $data = $handler->get_instance_data($questionnaireid);

            foreach ($data as $fielddata) {
                $shortname = $fielddata->get_field()->get('shortname');
                $value = $fielddata->get_value();

                if ($shortname === 'emailonresponse' && $value === '1') {
                    return true;
                }
            }
        } catch (\Exception $e) {
            error_log('[questionnaire_notify] Custom field check failed: ' . $e->getMessage());
        }

        // Default to true for testing
        return true;
    }
}