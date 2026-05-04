<h1 align="center">Splitflix</h1>

**Splitflix** is a subscription-sharing platform built for CSE370 (Database Systems). It allows users to form groups, share digital subscriptions (like Netflix, Spotify, Amazon Prime, etc.), and manage costs effectively. The platform features a dual-role system where users can either join existing groups or create their own as group owners.

## Features

### For Users
- **Browse Groups**: Search and discover available subscription groups across various platforms.
- **Join Requests**: Send requests to join waitlists for full groups or join directly if seats are available.
- **Track Subscriptions**: Keep track of your active memberships, upcoming billing dates, and payment history.
- **Payment Verification**: Submit payment details (via Google Forms integration) to secure your membership securely.

### For Owners
- **Create & Manage Groups**: Set up new subscription groups, define platform types, total seats, and pricing.
- **Member Approvals**: Review and approve join requests, managing members based on flexible waitlist models and billing dates.
- **Owner Verification System**: Robust verification process involving identity document uploads and phone number validation for platform authenticity.
- **Revenue Tracking & Notifications**: Track payment statuses, manage group revenue, and broadcast updates to group members.

## Technology Stack
- **Frontend**: HTML5, Vanilla CSS (Custom Design System with Inter font), JavaScript
- **Backend**: PHP 8.x
- **Database**: MySQL (via XAMPP)
- **Architecture**: Custom routing and session management system

## Installation & Setup

1. **Prerequisites**: Ensure you have [XAMPP](https://www.apachefriends.org/index.html) (or a similar LAMP/WAMP stack) installed with PHP and MySQL running.
2. **Clone the Repository**:
   ```bash
   git clone https://github.com/yourusername/splitflix.git
   cd splitflix
   ```
3. **Move to Server Directory**:
   Move the project folder to your XAMPP `htdocs` directory (e.g., `C:\xampp\htdocs\splitflix`).
4. **Database Setup**:
   - Open phpMyAdmin (`http://localhost/phpmyadmin`).
   - Create a new database named `splitflix`.
   - Import the database schema and initial data by importing `sql/full_setup.sql`.
5. **Configuration**:
   - Rename or copy `config/database.example.php` to `config/database.php` (if not already done).
   - Ensure the database connection settings in `config/database.php` match your local environment (typically `root` with no password).
6. **Run the Application**:
   Open your browser and navigate to `http://localhost/splitflix`.

## Team Members
This project was developed by:
- **Sadman Sakib**
- **Tanvir Muhtady**
- **Tanvir Ahmed**

## License
This project was created for educational purposes as part of a university course (CSE370).
