# Moodle Plugin: Questionnaire Submission Notifier

This is a **local plugin** for Moodle 4.4+ that automatically sends an email confirmation to users when they submit a response to a **Questionnaire activity** (`mod_questionnaire`). It is intended to be used in scenarios where the questionnaire is used as a course application or enrollment request form.

---

## 📌 Features

- Hooks into the Moodle event system via `mod_questionnaire\event\response_submitted`
- Sends a customizable confirmation email to the user
- Includes the course name and user's first name
- Designed to be simple, lightweight, and easy to extend
- Compatible with Moodle 4.4 and PHP 8+

---

## 🔧 Installation

1. Clone or download this repository into your Moodle instance at:
