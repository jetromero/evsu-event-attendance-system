# Dual-Sync Feature Setup Guide

## Overview

The dual-sync feature automatically synchronizes user registration data between two different Supabase projects. When a user registers through the "Create EVSU Account" page, their data is saved in both the primary and secondary Supabase databases.

## Features

- **Automatic Sync**: New user registrations are automatically synced to both databases
- **Manual Sync**: Individual users or all users can be manually synced through the admin panel
- **Error Handling**: Failed secondary syncs don't prevent primary registration from completing
- **Connection Testing**: Admin tools to test connections to both databases
- **Statistics Dashboard**: View sync status and user counts for both databases
- **Enable/Disable Toggle**: Dual-sync can be easily enabled or disabled

## Configuration

### Primary Supabase Project

```php
$supabase_url = 'https://tlpllfglbtjxjwdvqxmc.supabase.co';
$supabase_key = 'your_primary_anon_key_here';
```

### Secondary Supabase Project

```php
$supabase_secondary_url = 'https://zegomgvvlgdijepeyzjp.supabase.co';
$supabase_secondary_key = 'your_secondary_service_role_key_here';
```

### Enable/Disable Sync

```php
$enable_dual_sync = true; // Set to false to disable dual-sync
```

## Prerequisites

### Secondary Supabase Project Setup

1. **Create identical users table structure** in the secondary project:

   ```sql
   CREATE TABLE public.users (
       id UUID PRIMARY KEY,
       email VARCHAR(255) UNIQUE NOT NULL,
       first_name VARCHAR(100) NOT NULL,
       last_name VARCHAR(100) NOT NULL,
       course VARCHAR(100) NOT NULL,
       year_level VARCHAR(20) NOT NULL,
       section VARCHAR(10) NOT NULL,
       role VARCHAR(20) DEFAULT 'student',
       created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
       updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
   );
   ```

2. **Set up Row Level Security (RLS) policies** to allow service role access:

   ```sql
   -- Enable RLS
   ALTER TABLE public.users ENABLE ROW LEVEL SECURITY;

   -- Allow service role to insert/update/select
   CREATE POLICY "Service role can manage users" ON public.users
   USING (auth.role() = 'service_role')
   WITH CHECK (auth.role() = 'service_role');
   ```

3. **Configure authentication** in the secondary project (optional for auth sync):
   - Enable email/password authentication
   - Configure email templates if needed

## How It Works

### Registration Process

1. **User submits registration form** on the "Create EVSU Account" page
2. **Primary registration** occurs in the main Supabase project
3. **User profile creation** in primary database with sync to secondary
4. **Secondary auth registration** (if dual-sync enabled)
5. **Secondary profile sync** via the `createUserProfile()` function

### Error Handling

- Primary registration always takes precedence
- Secondary sync failures are logged but don't prevent registration
- Failed syncs can be retried manually through the admin panel

### Authentication Strategy

- **Primary project**: Uses anon key for regular operations
- **Secondary project**: Uses service role key to bypass RLS restrictions
- **User sessions**: Only stored for primary project (users login to primary only)

## Admin Management

### Access the Dual-Sync Admin Panel

1. Login as an admin user
2. Navigate to **Admin Dashboard**
3. Click **Dual-Sync Management** or go to `dual_sync_admin.php`

### Available Admin Functions

1. **View Sync Statistics**

   - Current sync status (enabled/disabled)
   - User count in both databases
   - Sync difference indicator

2. **Test Connections**

   - Verify connectivity to both Supabase projects
   - Check database access permissions

3. **Sync Individual User**

   - Manually sync a specific user by UUID
   - Useful for fixing specific sync issues

4. **Bulk Sync All Users**
   - Sync all users from primary to secondary
   - Updates existing users in secondary database

## Security Considerations

### Service Role Key Usage

- The secondary project uses a **service role key** for admin-level access
- This key bypasses Row Level Security policies
- Store securely and never expose in client-side code

### Data Protection

- Both databases should have identical security policies
- Consider encryption for sensitive data
- Regular backups of both databases recommended

### Access Control

- Dual-sync admin functions are restricted to admin users only
- Regular audit of admin user accounts recommended

## Troubleshooting

### Common Issues

1. **"Connection failed" errors**

   - Check Supabase URLs and keys
   - Verify network connectivity
   - Ensure service role key has proper permissions

2. **"User not synced" warnings**

   - Check secondary database table structure
   - Verify RLS policies allow service role access
   - Review error logs for specific issues

3. **Sync count differences**
   - Use bulk sync to synchronize all users
   - Check for failed registrations in logs
   - Verify identical table structures

### Debug Steps

1. **Check error logs** in server logs and browser console
2. **Test connections** using the admin panel
3. **Verify credentials** in `supabase_config.php`
4. **Check database tables** exist in both projects
5. **Review RLS policies** in secondary project

## File Structure

```
/
├── supabase_config.php          # Main configuration with dual-sync
├── dual_sync_admin.php          # Admin management interface
├── admin_dashboard.php          # Updated with dual-sync link
├── index.php                    # Registration form (uses dual-sync)
└── DUAL_SYNC_SETUP.md          # This documentation
```

## API Functions

### Core Functions

- `getSupabaseClient()` - Primary database client
- `getSecondarySupabaseClient()` - Secondary database client
- `registerUser($userData)` - Registration with dual-sync
- `createUserProfile($authUserId, $userData)` - Profile creation with sync
- `syncUserToSecondary($userId)` - Manual user sync

### Admin Functions

- `testDualSyncConnections()` - Test both database connections
- `getSyncStatistics()` - Get sync status and counts

## Future Enhancements

- **Real-time sync monitoring** with webhooks
- **Conflict resolution** for simultaneous updates
- **Sync event logging** for audit trails
- **Automated sync health checks**
- **Multi-directional sync** support

## Support

For issues or questions about the dual-sync feature:

1. Check the error logs first
2. Use the connection test in admin panel
3. Verify both database configurations
4. Review this documentation
5. Check Supabase project settings and permissions
