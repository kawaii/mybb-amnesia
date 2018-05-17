# Amnesia

A MyBB Native Extension to Support Information Administration. Aims to implement main policies introduced by the EU's General Data Protection Regulation (GDPR).

### Features

- #### Consent
  New and present users can be asked to accept a Privacy Policy document with provided date before using the forums.

- #### Data Portability
  Allows users to export personal data related to their account and content.

- #### Erasure
  Allows users to request account anonymization or deletion to remove their personal data.

### Dependencies
- MyBB 1.8.x
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

- #### Event Hooks
  The extension introduces new plugin hooks executed with certain actions:
  - `amnesia_reload_privacy_policy_date`
  - `amnesia_create_erasure_request`
  - `amnesia_cancel_erasure_request`
  - `amnesia_verify_erasure_request`
  - `amnesia_anonymize_user_start`
  - `amnesia_anonymize_user_end`

### Plugin Management Events
- **Install:**
  - Settings populated
- **Uninstall:**
  - Settings deleted
- **Activate:**
- **Deactivate:**


### Development Mode
The plugin can operate in development mode, where plugin templates are being fetched directly from the `templates/` directory - set `amnesia\DEVELOPMENT_MODE` to `true` in `inc/plugins/amnesia.php`.
