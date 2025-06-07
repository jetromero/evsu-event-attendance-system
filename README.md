# EVSU Student Portal

A PHP-based student portal system for Eastern Visayas State University (EVSU) with user authentication, registration, and dashboard functionality.

## Features

- **User Registration**: Students can create accounts with email, name, year level, and section
- **User Authentication**: Secure login system with password hashing
- **Dashboard**: Personalized dashboard showing user information and quick actions
- **Role-based Access**: Support for student and admin roles
- **Responsive Design**: Mobile-friendly interface using modern CSS
- **Database Integration**: Supabase (PostgreSQL) with Row Level Security and real-time capabilities

## Database Schema

The system uses the following main table structure:

```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    role ENUM('student', 'admin') DEFAULT 'student',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    year_level VARCHAR(20) NOT NULL,
    section VARCHAR(1) NOT NULL
);
```

## Setup Instructions

### 1. Prerequisites

- XAMPP, WAMP, or similar PHP development environment
- PHP 7.4 or higher with cURL extension enabled
- Supabase account and project
- Web browser

### 2. Installation

1. **Clone/Download the project** to your web server directory (e.g., `c:\xampp\htdocs\joyces`)

2. **Supabase Setup**:

   - Create a new project at [supabase.com](https://supabase.com)
   - Copy your project URL and anon key from the API settings
   - Run the `supabase_setup.sql` script in your Supabase SQL Editor
   - See `SUPABASE_SETUP.md` for detailed setup instructions

3. **Configuration**:

   - Open `supabase_config.php`
   - Update the Supabase connection settings:
     ```php
     $supabase_url = 'https://your-project-id.supabase.co';
     $supabase_key = 'your-anon-key-here';
     ```

4. **File Permissions**:
   - Ensure the web server has read/write permissions to the project directory

### 3. Testing the Setup

1. Navigate to `http://localhost/joyces/` in your web browser
2. Register a new account using an EVSU email address (@evsu.edu.ph)
3. Log in with your new credentials
4. Verify that the dashboard displays your information correctly

**Note**: Sample users from the MySQL version are not automatically available. You'll need to register new accounts or migrate existing data using the provided migration script.

### 4. Usage

1. Navigate to `http://localhost/joyces/` in your web browser
2. Use the registration form to create a new account or login with existing credentials
3. After successful login, you'll be redirected to the dashboard
4. The dashboard displays user information and provides navigation to various features

## File Structure

```
joyces/
├── index.php              # Main login/registration page (updated for Supabase)
├── dashboard.php          # User dashboard after login (updated for Supabase)
├── config.php             # Main configuration entry point
├── supabase_config.php    # Supabase configuration and helper functions
├── supabase_setup.sql     # Supabase database schema
├── migrate_to_supabase.php # Migration script from MySQL to Supabase
├── SUPABASE_SETUP.md      # Detailed Supabase setup guide
├── composer.json          # PHP package management
├── database_setup.sql     # Legacy MySQL schema (for reference)
├── README.md              # This file
├── src/
│   └── SupabaseClient.php # Custom Supabase client class
└── assets/
    ├── css/
    │   └── styles.css     # Main stylesheet
    ├── js/
    │   └── main.js        # JavaScript functionality
    └── img/
        └── bg-img.jpg     # Background image
```

## Security Features

- **Supabase Authentication**: Built-in secure authentication with JWT tokens
- **Row Level Security (RLS)**: Database-level security policies
- **Password Hashing**: Handled securely by Supabase Auth
- **XSS Protection**: Input sanitization with `htmlspecialchars()`
- **Session Management**: Secure session handling with Supabase tokens
- **Input Validation**: Server-side validation for all form inputs
- **HTTPS Support**: Built-in SSL/TLS support through Supabase

## Customization

### Adding New Fields

To add new fields to the user registration:

1. Update the database table structure
2. Modify the registration form in `index.php`
3. Update the PHP validation logic
4. Adjust the dashboard display in `dashboard.php`

### Styling

The system uses a combination of:

- External CSS framework (RemixIcons for icons)
- Custom CSS in `assets/css/styles.css`
- Inline styles for specific components

### Database Configuration

Update `supabase_config.php` with your Supabase project credentials. The system uses a custom Supabase client for secure API communication with built-in error handling.

## Future Enhancements

The database schema includes tables for future features:

- Course management
- Student enrollments
- Grade tracking
- Schedule management

## Troubleshooting

### Common Issues

1. **Supabase Connection Error**:

   - Check Supabase credentials in `supabase_config.php`
   - Verify your Supabase project is active
   - Ensure cURL extension is enabled in PHP

2. **Login Issues**:

   - Check if user exists in Supabase Auth and users table
   - Verify Supabase authentication is working
   - Check session configuration and JWT tokens

3. **Registration Issues**:

   - Verify email domain restrictions in Supabase
   - Check Row Level Security policies
   - Ensure users table has proper permissions

4. **Permission Errors**:
   - Ensure web server has proper file permissions
   - Check PHP error logs for detailed information
   - Verify Supabase API key permissions

## Support

For issues or questions, please check:

- PHP error logs
- Browser developer console
- Database error messages

## License

This project is developed for educational purposes for EVSU students and faculty.
