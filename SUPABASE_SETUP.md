# Supabase Setup Guide for EVSU Student Portal

This guide will help you migrate your EVSU Student Portal from MySQL to Supabase.

## Prerequisites

1. A Supabase account (sign up at [supabase.com](https://supabase.com))
2. PHP 7.4 or higher with cURL extension enabled
3. Your existing EVSU Student Portal project

## Step 1: Create a Supabase Project

1. Go to [supabase.com](https://supabase.com) and sign in
2. Click "New Project"
3. Choose your organization
4. Enter project details:
   - **Name**: `evsu-student-portal`
   - **Database Password**: Choose a strong password
   - **Region**: Choose the closest region to your users
5. Click "Create new project"
6. Wait for the project to be set up (this may take a few minutes)

## Step 2: Get Your Project Credentials

1. In your Supabase dashboard, go to **Settings** > **API**
2. Copy the following values:
   - **Project URL** (looks like: `https://your-project-id.supabase.co`)
   - **anon/public key** (starts with `eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...`)

## Step 3: Configure Your Project

1. Open `supabase_config.php` in your project
2. Replace the placeholder values with your actual Supabase credentials:

```php
// Your Supabase project URL (found in your Supabase dashboard)
$supabase_url = 'https://your-project-id.supabase.co';

// Your Supabase anon/public key (found in your Supabase dashboard under Settings > API)
$supabase_key = 'your-actual-anon-key-here';
```

## Step 4: Set Up the Database Schema

1. In your Supabase dashboard, go to the **SQL Editor**
2. Copy the contents of `supabase_setup.sql` from your project
3. Paste it into the SQL Editor
4. Click "Run" to execute the script

This will create:

- `users` table with proper Row Level Security (RLS) including course field
- `courses` table for future course management
- `enrollments` table for student enrollments
- `schedules` table for class schedules
- Proper security policies and triggers

**Note**: If you already have an existing users table without the course field, run the `add_course_field.sql` script first to add the course column.

## Step 5: Enable Authentication

1. In your Supabase dashboard, go to **Authentication** > **Settings**
2. Configure the following settings:

### Email Settings

- **Enable email confirmations**: Turn OFF (for development)
- **Enable email change confirmations**: Turn OFF (for development)
- **Secure email change**: Turn OFF (for development)

### Password Settings

- **Minimum password length**: 8
- **Password requirements**: Enable uppercase, lowercase, and numbers

### Site URL

- Set your site URL to: `http://localhost/joyces` (or your actual domain)

## Step 6: Configure Email Domain Restrictions (Optional)

To restrict registration to EVSU email addresses only:

1. Go to **Authentication** > **Settings**
2. Scroll down to **Email Domain Restrictions**
3. Add `evsu.edu.ph` to the allowed domains list

## Step 7: Test the Integration

1. Make sure your web server (XAMPP) is running
2. Navigate to your project: `http://localhost/joyces`
3. Try registering a new account with an EVSU email
4. Try logging in with the new account
5. Check that the dashboard displays user information correctly

## Step 8: Migrate Existing Data (Optional)

If you have existing users in your MySQL database, you can migrate them:

1. Uncomment the MySQL configuration in `config.php`
2. Create a migration script to transfer users from MySQL to Supabase
3. Use the Supabase client to insert existing user data

## Troubleshooting

### Common Issues

1. **cURL SSL Certificate Error**

   - The Supabase client is configured to bypass SSL verification for development
   - For production, ensure proper SSL certificates are configured

2. **Authentication Fails**

   - Check that your Supabase URL and API key are correct
   - Verify that the user exists in both `auth.users` and `public.users` tables

3. **Registration Fails**

   - Check the browser's developer console for error messages
   - Verify that email domain restrictions are properly configured
   - Check Supabase logs in the dashboard

4. **Database Connection Issues**
   - Verify your Supabase project is active and not paused
   - Check that the API key has the correct permissions

### Debugging

1. Enable PHP error logging in your development environment
2. Check the PHP error log for detailed error messages
3. Use the Supabase dashboard to monitor API requests and errors
4. Check the browser's network tab for failed requests

## Security Considerations

### For Production

1. **Enable SSL verification** in the SupabaseClient class
2. **Enable email confirmations** in Supabase Auth settings
3. **Set up proper CORS policies** in Supabase
4. **Use environment variables** for sensitive configuration
5. **Enable Row Level Security** policies (already configured in the setup script)

### Environment Variables

For production, consider using environment variables:

```php
$supabase_url = $_ENV['SUPABASE_URL'] ?? 'fallback-url';
$supabase_key = $_ENV['SUPABASE_ANON_KEY'] ?? 'fallback-key';
```

## Next Steps

1. **Customize the user interface** to match your institution's branding
2. **Implement additional features** like course enrollment, grade management
3. **Set up email templates** in Supabase for password resets and confirmations
4. **Configure production deployment** with proper SSL and domain settings
5. **Set up monitoring and analytics** using Supabase's built-in tools

## Support

- **Supabase Documentation**: [docs.supabase.com](https://docs.supabase.com)
- **Supabase Community**: [github.com/supabase/supabase/discussions](https://github.com/supabase/supabase/discussions)
- **PHP cURL Documentation**: [php.net/manual/en/book.curl.php](https://php.net/manual/en/book.curl.php)

## File Structure After Migration

```
joyces/
├── index.php              # Updated with Supabase auth
├── dashboard.php          # Updated to use Supabase
├── config.php             # Now includes Supabase config
├── supabase_config.php    # New: Supabase configuration
├── supabase_setup.sql     # New: Database schema for Supabase
├── SUPABASE_SETUP.md      # This setup guide
├── src/
│   └── SupabaseClient.php # New: Custom Supabase client
├── composer.json          # New: Package management
├── database_setup.sql     # Legacy: Original MySQL schema
├── README.md              # Updated project documentation
└── assets/                # Unchanged: CSS, JS, images
```
