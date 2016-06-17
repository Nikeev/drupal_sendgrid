## SUMMARY

This module integrates Drupal 8 mail system with SendGrid API.

Module mostly based on Drupal 8 version of Mandrill integration module. 
Some functionality was removed, some added and rewrited for SendGrid API.

At the moment module have quite basic functions:

* Send emails with attachments.
* Support custom tags as 'metadata' array.
* SendGrid mail template select.
* Queue. All messages could be added to queue and send later on cron worker.
Or you can also specify mail tags to send with queue and other mails will be send immedeately.

## INSTALLATION

This module require:
* Mail System (https://drupal.org/project/mailsystem)
* SendGrid PHP Library (https://github.com/sendgrid/sendgrid-php)

Optionally:
Composer Manager (https://drupal.org/project/composer_manager)

You could install and enable SendGrid PHP Library with Composer Manager.
If not, module will try to find sendgrid/vendor/autoload.php file and SendGrid class.

Then enable module as usual and enter SendGrid API key in module settings. And enable module to be used in Mail System settings.

