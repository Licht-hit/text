# Account Portal

This project contains account registration, email verification, login, and a simple signed-in dashboard.

## Setup

1. Start Apache and MySQL, then import `beginner_student_portal.sql` in phpMyAdmin.
2. Update the MySQL connection settings in `config/database.php` if needed.
3. Configure the SMTP values in `config/mail.php`. Do not commit real mail passwords or app passwords.
4. Ensure PHP's OpenSSL extension is enabled for TLS email delivery.
5. Open `http://localhost/not/`, create an account, and use the verification link delivered to that email address.
6. After verification, sign in. Successful login opens `dashboard.php`.

The database script creates only the `users` table required by login, registration, and verification. When upgrading an existing users table, it adds any missing email-verification fields and fills missing email values with reserved `.invalid` addresses. It does not delete existing user data or unrelated tables. Back up the database before manually removing any old tables.

The SQL adds the three original demo accounts only when their usernames/emails are not already present, and does not overwrite existing account passwords:

| Username | Password | Account type |
|---|---|---|
| `admin` | `admin123` | Admin |
| `juan` | `student123` | Student |
| `staff` | `staff123` | Staff |

## Account flow

- Registration validates the name, username, email, and password, stores a password hash, and creates a staff account.
- New accounts remain unable to sign in until their email verification link is used.
- If email delivery fails, configure SMTP and submit the same registration details again to resend the link.
- Verification links can only be used once.
- Login accepts either the username or email address.
- Signed-in users are routed to their role-specific dashboard: `admin/dashboard.php`, `staff/dashboard.php`, or `student/dashboard.php`.
- New registrations create staff accounts. Existing accounts keep their assigned roles.
- Each dashboard checks the signed-in role and provides navigation back to the dashboard, registration, and logout.
- Logging out ends the session and returns to login.
