# Splitflix

**Splitflix** is a subscription-sharing platform built for **CSE370 (Database Systems)**. Members form small groups around a streaming or music subscription (Netflix, Amazon Prime, Hotstar, HBO Max, Spotify), split the monthly cost, and manage payments, waitlists, reviews, notifications, recommendations, and group chat.

One account can use both views: **join groups as a member** and **create/manage groups as an owner**. There is no separate owner account type.

## Features

### Member view
- Browse platforms and see how many active groups have free seats.
- Request to join a group; full groups put the request on a waitlist.
- Track subscriptions, the next billing date, payment status, and the owner's contact details.
- Schedule a leave; the membership ends on the next billing date (applied automatically on the next page load).
- Review the group owner once per group.
- Read owner announcements, post movie/show recommendations, and chat with the group.

### Owner view
- Create groups on any platform with a plan description, seat limit, price per member, and validity window.
- Verify the profile by uploading an ID card and adding a phone number (a verified badge is shown to members).
- Approve waitlisted members during the 7-day window that follows each billing date.
- Mark each member's payment as cleared or uncleared.
- Edit the plan description and the payment-collection link.
- Broadcast notifications, review members, and manage the leaving queue.
- Chat with the group from the group management page.

## Technology stack

| Layer | Choice |
|---|---|
| Backend | PHP 8.x (mysqli, sessions) |
| Database | MySQL 8 / MariaDB 10.4+ |
| Frontend | HTML5, vanilla CSS, vanilla JavaScript (no framework, no build step) |
| Server | Apache via XAMPP (or any LAMP/WAMP stack) |

## Requirements

- PHP **8.0+** with the `mysqli` and `fileinfo` extensions enabled.
- MySQL 8 or MariaDB 10.4+.
- Apache with `mod_rewrite` **not** required. `AllowOverride` must allow `.htaccess` files in the project (the default XAMPP configuration does).
- A writable `uploads/` directory for verification images.

## Installation

1. **Get the code** into your web root:

   ```bash
   cd C:\xampp\htdocs
   git clone <repository-url> splitflix
   ```

2. **Create the database and tables.** The consolidated installer creates the database, all tables, indexes, and sample platforms:

   - In phpMyAdmin: **Import** → choose `sql/full_setup.sql` → **Go**.
   - Or from a terminal:

     ```bash
     mysql -u root -p < sql/full_setup.sql
     ```

   > `sql/full_setup.sql` is the **only** file needed for a new install. It includes the feature tables for notifications, recommendations, and chat.

3. **Configure the connection.** Copy the template and adjust the credentials if yours differ:

   ```bash
   copy config\database.example.php config\database.php
   ```

   The defaults (`localhost`, `root`, empty password, database `splitflix`) match a standard XAMPP install.

4. **Make uploads writable.** On Windows/XAMPP the default permissions normally work. On Linux/macOS:

   ```bash
   chmod -R 755 uploads
   ```

   `uploads/.htaccess` disables script execution inside the upload directory. Keep it in place.

5. **Open the app:** <http://localhost/splitflix/>. Sign up, then choose **View as User** or **View as Owner** from the role selection page.

### Upgrading an existing database

If the database was created before this version, run `sql/migrate_v2.sql` **once** instead of re-importing. It:

- creates `group_notifications`, `movie_recommendations`, and `group_chat` if missing;
- changes the review uniqueness key so an owner can review every member;
- removes the unused `users.role` column;
- recalculates every group's `seats_remaining` from the real member count.

Databases from older iterations may still contain a few unused columns/tables (`platforms.logo_emoji`, `subscription_group.contact_info`, `plans`). They are harmless; fresh installs do not create them.

## Project structure

```text
splitflix/
├── index.php                 # Role selection (member vs owner view)
├── auth/
│   ├── login.php
│   ├── signup.php
│   └── logout.php
├── user/
│   ├── dashboard.php         # Browse platforms
│   ├── groups.php            # Groups on one platform
│   ├── join_group.php        # POST: submit/waitlist a join request
│   ├── my_groups.php         # Subscriptions, reviews, notifications, chat
│   ├── leave_group.php       # POST: schedule a leave
│   ├── submit_review.php     # POST: review owner/member
│   ├── add_recommendation.php
│   ├── send_chat.php
│   └── chat_messages.php     # Chat polling endpoint
├── owner/
│   ├── dashboard.php         # Groups + verification
│   ├── create_group.php
│   ├── group_details.php     # Members, payments, waitlist, notifications
│   ├── accept_member.php     # JSON: approve a waitlisted member
│   ├── remove_member.php     # JSON
│   ├── cancel_leave.php      # JSON
│   ├── update_payment.php    # JSON
│   ├── update_description.php
│   ├── update_payment_link.php
│   ├── post_notification.php
│   ├── delete_notification.php
│   └── verify_profile.php    # ID-card upload + phone verification
├── assets/
│   ├── css/auth.css
│   ├── css/dashboard.css
│   └── includes/
│       ├── icons.php         # Inline platform SVGs
│       ├── security.php      # CSRF + flash messages
│       └── membership.php    # Seat recalculation + scheduled leaves
├── config/
│   ├── database.example.php
│   └── database.php          # Not committed
├── sql/
│   ├── full_setup.sql        # Fresh install (use this)
│   ├── migrate_v2.sql        # Upgrade an existing database
│   └── *.sql                 # Historical/legacy migration scripts
├── uploads/                  # ID-card images (script execution disabled)
└── README.md
```

## Database schema

| Table | Purpose |
|---|---|
| `users` | Accounts, contact details, hashed passwords, verification state |
| `platforms` | Netflix, Prime, Hotstar, HBO Max, Spotify + brand colour |
| `subscription_group` | Owner, platform, plan text, seats, price, validity, payment link |
| `group_members` | Membership state (`active`, `waitlisted`, `rejected`), payment state, scheduled leave |
| `reviews` | Member→owner and owner→member ratings, one per reviewer/reviewee/group |
| `group_notifications` | Owner announcements to a group |
| `movie_recommendations` | Member recommendations shared inside a group |
| `group_chat` | Group chat messages (members and owner) |

### Business rules

- The owner occupies one seat. `seats_remaining` is always derived from `max_members - 1 - active_members`.
- Joining a group creates a **waitlist** entry; the owner approves it.
- An owner can approve members only during the first **7 days after the monthly billing date**. The billing date is the day-of-month of `validity_start`.
- A member who schedules a leave stays active until the next billing date. On or after that date, the next page load applies the leave and recalculates the seat.
- A member can review the owner of a group once; the owner can review each active member once.
- Reviews are only accepted from the correct side of the group (member → owner, owner → member).

## Security notes

- All database access uses prepared statements.
- Passwords are stored with `password_hash()` and checked with `password_verify()`.
- Login regenerates the session ID to prevent session fixation.
- Every state-changing form and AJAX request is protected by a per-session CSRF token.
- ID-card uploads are validated by MIME type, limited to 5 MB, saved with a server-generated filename, and served from a directory that denies script execution.
- Join, leave, and review actions use POST (never GET).

For production use: serve over HTTPS, set a strong MySQL password, keep `config/database.php` out of version control, and delete the sample files in `uploads/`.

## Troubleshooting

| Symptom | Fix |
|---|---|
| “Table 'splitflix.group_notifications' doesn't exist” | Import `sql/full_setup.sql`, or run `sql/migrate_v2.sql` on the old database. |
| “Connection failed” | Check `config/database.php`; confirm MySQL is running. |
| ID upload fails | Ensure `uploads/` exists and is writable, and that `upload_max_filesize`/`post_max_size` in `php.ini` are at least 5M. |
| Blank page / 500 | Check the Apache error log and `php.ini` for a missing `fileinfo` or `mysqli` extension. |
| Pages look unlocked / stale styles | Hard-refresh (`Ctrl+F5`) to bypass the browser cache. |

## Team

- Sadman Sakib
- Tanvir Muhtady
- Tanvir Ahmed

## License

Created for educational purposes as part of CSE370.
