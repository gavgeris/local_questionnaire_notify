<?php
// File: local/questionnaire_notify/classes/observer.php
namespace local_questionnaire_notify;

use mod_questionnaire\event\attempt_submitted;

class observer {

    public static function on_attempt_submitted(attempt_submitted $event): void {
        global $DB;
        error_log('[questionnaire_notify] OBSERVER CALLED!');
        // Add a notification that will show on screen
        \core\notification::add('Questionnaire observer triggered!', \core\notification::INFO);

        error_log('[questionnaire_notify] Event triggered - attempt_submitted');
        error_log('[questionnaire_notify] Event data: ' . print_r($event->get_data(), true));

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

            error_log("[questionnaire_notify] Questionnaire: {$questionnaire->name}, Course: {$course->fullname}");

            // Check if email notifications are enabled
//            $sendemail = self::should_send_email($questionnaire->id);
            $sendemail = true;

            if (!$sendemail) {
                error_log("[questionnaire_notify] Email sending is disabled for questionnaire ID: {$questionnaire->id}");
                return;
            }

            // Get user details
            $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);

            // Send email
            $subject = get_string('emailsubject', 'local_questionnaire_notify', $course->fullname);
            $body = get_string('emailbody', 'local_questionnaire_notify', [
                'firstname' => $user->firstname,
                'coursename' => $course->fullname
            ]);

            if (email_to_user($user, \core_user::get_support_user(), $subject, $body)) {
                error_log("[questionnaire_notify] Email sent successfully to {$user->email}");
            } else {
                error_log("[questionnaire_notify] Failed to send email to {$user->email}");
            }

        } catch (\Throwable $e) {
            error_log('[questionnaire_notify] Exception: ' . $e->getMessage());
            error_log('[questionnaire_notify] Stack trace: ' . $e->getTraceAsString());
        }
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

        // Default to true for testing - remove this line in production
        return true;
    }
}