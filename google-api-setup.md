# 🚀 EASY Google API Setup for EVSU Event Attendance System

## ✅ What You'll Get

After following this guide, you'll be able to:

- Export attendance reports directly to Google Sheets
- Save reports to Google Drive automatically
- Share reports with others easily

## 📋 Before You Start

You need:

- A Google account (gmail.com)
- 15 minutes of your time
- Admin access to your EVSU system

---

## 🎯 STEP 1: Create Google Cloud Project (5 minutes)

### 1.1 Go to Google Cloud Console

- Open: https://console.cloud.google.com/
- Sign in with your Google account

### 1.2 Create New Project

- Click the **blue "Create Project"** button
- Project name: `EVSU Attendance System`
- Click **"CREATE"**
- ⏰ Wait 30 seconds for project creation

### 1.3 Confirm Project Selection

- Top left should show: **"EVSU Attendance System"**
- If not, click the dropdown and select your project

---

## 🔧 STEP 2: Enable Google APIs (3 minutes)

### 2.1 Go to APIs Library

- Left menu → **"APIs & Services"** → **"Library"**

### 2.2 Enable Google Sheets API

- Search box: type `Google Sheets API`
- Click **"Google Sheets API"**
- Click **blue "ENABLE"** button
- ✅ You'll see "API enabled"

### 2.3 Enable Google Drive API

- Search box: type `Google Drive API`
- Click **"Google Drive API"**
- Click **blue "ENABLE"** button
- ✅ You'll see "API enabled"

---

## 👤 STEP 3: Create Service Account (4 minutes)

### 3.1 Go to Credentials

- Left menu → **"APIs & Services"** → **"Credentials"**

### 3.2 Create Service Account

- Click **"+ CREATE CREDENTIALS"**
- Select **"Service account"**

### 3.3 Fill Service Account Details

- **Service account name:** `evsu-reports`
- **Service account ID:** (auto-filled, leave as is)
- **Description:** `For EVSU attendance reports`
- Click **"CREATE AND CONTINUE"**

### 3.4 Skip Role Assignment

- Click **"CONTINUE"** (skip this step)
- Click **"DONE"**

---

## 🔑 STEP 4: Download Credentials File (2 minutes)

### 4.1 Find Your Service Account

- You should see: `evsu-reports@your-project.iam.gserviceaccount.com`
- Click on it

### 4.2 Create Key

- Click **"KEYS"** tab
- Click **"ADD KEY"** → **"Create new key"**
- Select **"JSON"** format
- Click **"CREATE"**

### 4.3 Save the File

- A JSON file downloads automatically
- **IMPORTANT:** Rename it to exactly: `google-credentials.json`
- **IMPORTANT:** Move it to your EVSU project folder (same folder as `reports.php`)

---

## 💻 STEP 5: Install Required Code (1 minute)

### 5.1 Install Google API Library

Open Command Prompt (Windows) or Terminal (Mac/Linux) in your EVSU project folder and run:

```bash
composer require google/apiclient:^2.0
```

**Don't have Composer?**

- Windows: Download from https://getcomposer.org/Composer-Setup.exe
- Install and restart Command Prompt

---

## ✅ STEP 6: Test Your Setup

### 6.1 Check Files Are in Place

Your EVSU folder should have:

```
📁 Your EVSU Project Folder/
├── 📄 reports.php
├── 📄 google-credentials.json  ← NEW FILE
├── 📁 vendor/                  ← NEW FOLDER
└── 📄 other files...
```

### 6.2 Test the Integration

1. Open your EVSU admin dashboard
2. Go to **Reports** page
3. Click **"Test Google API"** button (if available)
4. OR try generating a report with **"Google Sheets"** export

---

## 🚨 TROUBLESHOOTING

### ❌ "File not found" error

**Problem:** `google-credentials.json` not found
**Solution:**

1. Make sure file is named exactly `google-credentials.json`
2. Make sure it's in the same folder as `reports.php`
3. Check file isn't inside a subfolder

### ❌ "API not enabled" error

**Problem:** APIs not enabled in Google Cloud
**Solution:**

1. Go back to Google Cloud Console
2. Check both APIs are enabled (green checkmark)
3. Wait 5 minutes and try again

### ❌ "Permission denied" error

**Problem:** Service account has no access
**Solution:**

1. Go to Google Cloud Console → IAM & Admin → IAM
2. Find your service account email
3. Click edit (pencil icon)
4. Add role: **"Editor"**
5. Save

### ❌ "SSL certificate" error (Windows/XAMPP)

**Problem:** Outdated certificates
**Solution:**

1. Download: https://curl.se/ca/cacert.pem
2. Save as `C:\xampp\apache\bin\cacert.pem`
3. Edit `C:\xampp\php\php.ini`
4. Find: `;curl.cainfo =`
5. Change to: `curl.cainfo = "C:\xampp\apache\bin\cacert.pem"`
6. Restart XAMPP

---

## 🎉 Success! What's Next?

### Your reports can now:

- ✅ Export to Google Sheets (shareable links)
- ✅ Save to Google Drive (organized storage)
- ✅ Be accessed from anywhere
- ✅ Be shared with other staff

### Security Note:

- Never share your `google-credentials.json` file
- Don't upload it to GitHub or other public places
- Keep it in your project folder only

---

## 📞 Still Need Help?

### Common Fixes:

1. **Restart XAMPP** after installing composer
2. **Wait 5-10 minutes** after enabling APIs
3. **Double-check file names** are exact
4. **Verify Google account** has proper access

### If nothing works:

- Use **CSV export** (always works)
- Google integration is optional
- Contact system administrator

---

_This guide was written to be foolproof. If you're still having issues, the problem might be with your local environment (XAMPP/server configuration) rather than Google setup._
