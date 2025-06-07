# Events Database Setup Guide

This guide explains how to set up the database for the EVSU Event Attendance System's event management functionality.

## 📋 Required Database Tables

### 1. Events Table

The events table stores all event information:

**Fields:**

- `id` (UUID, Primary Key)
- `title` (VARCHAR(255), Required)
- `description` (TEXT)
- `event_date` (DATE, Required)
- `start_time` (TIME, Required)
- `end_time` (TIME, Required)
- `location` (VARCHAR(255))
- `max_attendees` (INTEGER, Optional)
- `created_by` (UUID, References users.id)
- `status` (VARCHAR(20), Default: 'active')
- `created_at` (TIMESTAMP)
- `updated_at` (TIMESTAMP)

**Status Values:**

- `active` - Event is active and accepting registrations
- `inactive` - Event is created but not yet active
- `completed` - Event has finished
- `cancelled` - Event has been cancelled

### 2. Attendance Table

The attendance table tracks who attended which events:

**Fields:**

- `id` (UUID, Primary Key)
- `user_id` (UUID, References users.id)
- `event_id` (UUID, References events.id)
- `attendance_date` (TIMESTAMP)
- `check_in_method` (VARCHAR(20), Default: 'qr_code')
- `notes` (TEXT)

## 🚀 Setup Instructions

### Step 1: Create Base Tables

Run the `event_attendance_tables.sql` script in your Supabase SQL Editor.

### Step 2: Update Status Constraint

Run the `update_events_table.sql` script to add support for the 'inactive' status:

```sql
-- Drop existing constraint and add new one with 'inactive' status
ALTER TABLE public.events DROP CONSTRAINT IF EXISTS events_status_check;
ALTER TABLE public.events ADD CONSTRAINT events_status_check
    CHECK (status IN ('active', 'inactive', 'cancelled', 'completed'));
```

### Step 3: Configure Row Level Security

Run the `update_events_rls_policies.sql` script to set up proper security:

```sql
-- Disable RLS for service key access (recommended for PHP apps)
ALTER TABLE public.events DISABLE ROW LEVEL SECURITY;
ALTER TABLE public.attendance DISABLE ROW LEVEL SECURITY;
```

### Step 4: Verify Setup

Run the `verify_events_database.sql` script to ensure everything is working correctly.

## 🔧 Key Database Features

### Views

Two helpful views are created:

1. **event_attendance_summary** - Shows attendance statistics for each event
2. **user_attendance_history** - Shows attendance history for users

### Indexes

Performance indexes are created on:

- `events.event_date`
- `events.status`
- `events.created_by`
- `attendance.user_id`
- `attendance.event_id`
- `attendance.attendance_date`

### Triggers

- Auto-update `updated_at` field when events are modified

## 🔒 Security Considerations

### Row Level Security (RLS)

- **Disabled by default** for PHP service key access
- Can be re-enabled later with proper JWT authentication
- Application-level security is implemented in PHP code

### Admin Permissions

- Only users with `role = 'admin'` can create/edit/delete events
- All users can view active events
- Users can only view their own attendance records

## 🧪 Testing the Setup

1. **Create a test event:**

```sql
INSERT INTO public.events (title, description, event_date, start_time, end_time, location, status)
VALUES ('Test Event', 'Testing the system', '2024-12-31', '10:00:00', '11:00:00', 'Test Location', 'active');
```

2. **Verify the event appears in views:**

```sql
SELECT * FROM event_attendance_summary;
```

3. **Test all status values:**

```sql
-- Should all work without errors
UPDATE public.events SET status = 'active' WHERE title = 'Test Event';
UPDATE public.events SET status = 'inactive' WHERE title = 'Test Event';
UPDATE public.events SET status = 'completed' WHERE title = 'Test Event';
UPDATE public.events SET status = 'cancelled' WHERE title = 'Test Event';
```

## 📁 Database Files Summary

1. **event_attendance_tables.sql** - Initial table creation
2. **update_events_table.sql** - Add 'inactive' status support
3. **update_events_rls_policies.sql** - Configure security policies
4. **verify_events_database.sql** - Verify setup is correct

## ✅ After Setup

Once the database is set up, the following features will work:

- ✅ Admin can create events via `create_event.php`
- ✅ Events appear in `events.php` management page
- ✅ Events show in admin dashboard statistics
- ✅ Students can view upcoming events
- ✅ QR code scanning can record attendance
- ✅ Attendance statistics are calculated automatically

## 🎯 Next Steps

1. Run the database setup scripts in order
2. Create an admin user (if not already done)
3. Test creating an event through the web interface
4. Verify events appear correctly in the events management page
