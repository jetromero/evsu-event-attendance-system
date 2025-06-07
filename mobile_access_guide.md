# 📱 Mobile Access Guide for QR Scanner

## 🎯 Your Network Information

- **Your Computer IP**: `192.168.254.100`
- **Network**: `192.168.254.x`
- **XAMPP Status**: SSL Module Enabled ✅

## 🔧 **Method 1: Enable HTTPS (Recommended)**

### Step 1: Configure XAMPP Apache

1. **Open XAMPP Control Panel**
2. **Stop Apache** if it's running
3. Click **"Config"** next to Apache
4. Select **"Apache (httpd.conf)"**

### Step 2: Enable SSL (if not already enabled)

Look for these lines and make sure they are **UNCOMMENTED** (no # at the beginning):

```apache
LoadModule ssl_module modules/mod_ssl.so
Include conf/extra/httpd-ssl.conf
```

### Step 3: Restart Apache

1. Save the httpd.conf file
2. **Start Apache** in XAMPP Control Panel
3. You should now see **both ports**: `Apache (80, 443)`

### Step 4: Test HTTPS Access

From your computer, test: `https://localhost/joyces/qr_scanner.php`

## 📱 **Accessing from Your Phone**

### Option A: HTTPS Access (Best for Camera)

1. **Connect your phone to the same WiFi network**
2. **Open browser on your phone**
3. **Navigate to**: `https://192.168.254.100/joyces/qr_scanner.php`

#### ⚠️ Expected Security Warning

Your phone will show a security warning because we're using a self-signed certificate:

**For Chrome/Safari:**

- Tap **"Advanced"**
- Tap **"Proceed to 192.168.254.100 (unsafe)"**
- OR tap **"Add Exception"**

**For Firefox:**

- Tap **"Advanced"**
- Tap **"Accept the Risk and Continue"**

#### ✅ After accepting the warning:

- The QR scanner page should load
- Camera access should work properly
- You can scan QR codes normally

### Option B: HTTP Access (Manual Entry Alternative)

If HTTPS doesn't work, you can use: `http://192.168.254.100/joyces/qr_scanner.php`

The page will detect mobile access and show:

- ✅ **Manual QR Entry Option** - Enter student info manually
- ✅ **Setup instructions** for HTTPS
- ✅ **Alternative methods**

## 🛠️ **Troubleshooting**

### Problem: "This site can't be reached"

**Solutions:**

1. **Check WiFi**: Make sure both devices are on the same network
2. **Check IP**: Verify your computer's IP with `ipconfig`
3. **Check Firewall**: Windows Firewall might be blocking Apache
4. **Check Apache**: Make sure Apache is running in XAMPP

### Problem: Camera permission denied on mobile

**Solutions:**

1. **Use HTTPS**: Only HTTPS allows camera access on mobile
2. **Accept security certificate**: You must accept the self-signed certificate
3. **Refresh page**: Sometimes permissions need a page refresh
4. **Check browser settings**: Ensure camera permissions are allowed

### Problem: HTTPS not working

**Fallback options:**

1. **Use Manual Entry**: The app now includes manual QR data entry
2. **Use Computer**: Scan QR codes directly on the computer
3. **Use Localhost**: Access from the computer running XAMPP

## 🎯 **Quick Test Steps**

### Test 1: Basic Connectivity

```
From phone browser: http://192.168.254.100/joyces/
```

Should show your website homepage.

### Test 2: HTTPS Access

```
From phone browser: https://192.168.254.100/joyces/qr_scanner.php
```

Should show QR scanner (after accepting certificate).

### Test 3: Camera Access

1. Navigate to HTTPS URL
2. Accept security certificate
3. Tap "Start Camera"
4. Allow camera permissions
5. Point at QR code

## 🔐 **Security Notes**

- ✅ **Local Network Only**: This setup only works on your local network
- ✅ **Development Use**: Perfect for testing and development
- ⚠️ **Self-Signed Certificate**: Browsers will show warnings (this is normal)
- 🚫 **Not for Production**: Don't use self-signed certificates in production

## 📞 **Need Help?**

If you're still having issues:

1. **Check Network**: `ping 192.168.254.100` from your phone's terminal app
2. **Check Ports**: Make sure Apache shows `(80, 443)` in XAMPP
3. **Check Firewall**: Temporarily disable Windows Firewall to test
4. **Use Manual Entry**: The app now includes a manual entry option for backup

## 🎉 **Success Indicators**

You'll know it's working when:

- ✅ Phone can access `https://192.168.254.100/joyces/qr_scanner.php`
- ✅ Camera permission prompt appears
- ✅ Video feed shows in the browser
- ✅ QR codes are detected and processed
- ✅ Student attendance is recorded successfully

---

**Your URLs to try:**

- Computer: `http://localhost/joyces/qr_scanner.php`
- Phone HTTP: `http://192.168.254.100/joyces/qr_scanner.php`
- Phone HTTPS: `https://192.168.254.100/joyces/qr_scanner.php` ⭐ **Best option**
