# Amnesia

**A MyBB Native Extension to Support Information Administration.** Aims to implement main policies introduced by the EU's General Data Protection Regulation (GDPR).

### Features

- #### Consent
  New and present users can be asked to accept a Privacy Policy document with provided date before using the forums.

- #### Data Portability
  Allows users to export personal data related to their account and content.

- #### Erasure
  Allows users to request account anonymization or deletion to remove their personal data.

### Dependencies
- MyBB >= 1.8.15
- https://github.com/frostschutz/MyBB-PluginLibrary
- PHP >= 7.1

### 3rd Party Integration
- #### Personal Data Fields
  - Register database fields storing personal data in the `mybb_users` table using the `amnesia_personal_account_data_fields` hook by appending field names to the array and, if applicable, use the MyBB's `datahandler_user_clear_profile` hook executed in the `UserDataHandler::clear_profile()` method that removes redundant information.

  - Register database fields storing personal data in other tables using the `amnesia_personal_data_field_definitions` and `amnesia_personal_data_sensitive_field_definitions` hooks by appending field definitions to arrays of standard and sensitive fields, respectively. Sensitive fields hold information that board administrators may wish to retain for security reasons (preventing e.g. logs of malicious activity from being purged).
  ```
  $TABLE_NAME => [
      'fields' => [
          $FIELD_NAME => [
              'type' => $FIELD_TYPE,
              'anonymizedValue' => $VALUE_AFTER_ANONYMIZATION,
          ],
          ...
      ],
      'userIdSelector' => $USER_ID_FIELD,
  ],
  ...
  ```
  The multidimensional arrays with table names as keys (first level) contain a list of personal data fields (`fields`) with arbitrary type identifiers (`type`) used to classify values and group unique entries when exporting data. The user ID selector (`userIdSelector`) indicates a column that will be used to match a user by their ID.

  - Register output formatting for custom field types with the `amnesia_format_database_value_for_output` hook taking an array with `type` and `value` elements a parameter. The overwritten `value` element will be used in final output for the user. Custom formatting for values stored in the `users` table can be registered with the `amnesia_format_user_account_data_for_output` hook accepting an array of user row values that can be overwritten.

- #### Event Hooks
  The extension introduces new plugin hooks executed with certain actions:
  - `amnesia_set_privacy_policy_last_read_for_user`
  - `amnesia_reload_privacy_policy_date`
  - `amnesia_create_erasure_request`
  - `amnesia_cancel_erasure_request`
  - `amnesia_verify_erasure_request`
  - `amnesia_complete_erasure_request`
  - `amnesia_approve_erasure_request`
  - `amnesia_schedule_erasure_request`
  - `amnesia_anonymize_user_start`
  - `amnesia_anonymize_user_end`
  - `amnesia_create_export_request`
  - `amnesia_invalidate_export_request`
  - `amnesia_invalidate_user_export_requests`
  - `amnesia_verify_export_request`
  - `amnesia_complete_export_request`

### Plugin Management Events
- **Install:**
  - Database structure created/altered
  - Settings populated
  - Cache entries created
  - Tasks registered
- **Uninstall:**
  - Database structure & data deleted/restored
  - Settings deleted
  - Cache entries removed
  - Tasks removed
- **Activate:**
  - Templates & stylesheets inserted/altered
- **Deactivate:**
  - Templates & stylesheets removed/restored

### Security Design
User requests for Personal Data Erasure and Export are verified by providing the account password and a verification code (link) sent to the associated email address on success.

The Export mechanism requires the password to be provided first, within the uninterrupted forum usage session, to reduce phishing-related attacks and the verification code to be provided within the same browsing session (using an additional session cookie) to prevent data from being exported by third parties with email and device (with active user session) access alone. Verification attempts for logged-in users without the additional session cookie invalidate the associated request. The data can only be accessed once for every export operation. Subsequent requests invalidate all previous ones for the user. Unverified requests expire after 24 hours.

Logs for completed Erasure and Export operations (not containing personal data) are not being pruned from the database during normal usage.

### Development Mode
The plugin can operate in development mode, where plugin templates are being fetched directly from the `templates/` directory - set `amnesia\DEVELOPMENT_MODE` to `true` in `inc/plugins/amnesia.php`.
