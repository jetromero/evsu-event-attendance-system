@echo off
echo ========================================
echo XAMPP HTTPS Setup for Mobile Access
echo ========================================
echo.
echo Your Local IP: 192.168.254.100
echo.
echo This script will help you set up HTTPS access for mobile devices.
echo.

echo Step 1: Checking XAMPP Apache configuration...
echo.

set XAMPP_PATH=C:\xampp
set APACHE_CONF=%XAMPP_PATH%\apache\conf\httpd.conf
set SSL_CONF=%XAMPP_PATH%\apache\conf\extra\httpd-ssl.conf

echo Checking if SSL module is enabled...
findstr /C:"LoadModule ssl_module" "%APACHE_CONF%" >nul
if %errorlevel%==0 (
    echo ✓ SSL module is already enabled
) else (
    echo ✗ SSL module is NOT enabled
    echo.
    echo To enable SSL manually:
    echo 1. Open %APACHE_CONF%
    echo 2. Find and uncomment: LoadModule ssl_module modules/mod_ssl.so
    echo 3. Find and uncomment: Include conf/extra/httpd-ssl.conf
)

echo.
echo Checking if HTTPS is configured...
if exist "%SSL_CONF%" (
    echo ✓ SSL configuration file exists
) else (
    echo ✗ SSL configuration file NOT found
)

echo.
echo ========================================
echo NEXT STEPS:
echo ========================================
echo.
echo 1. Start XAMPP Control Panel
echo 2. Stop Apache if running
echo 3. Click "Config" next to Apache
echo 4. Select "Apache (httpd.conf)"
echo.
echo 5. Find these lines and UNCOMMENT them (remove #):
echo    #LoadModule ssl_module modules/mod_ssl.so
echo    #Include conf/extra/httpd-ssl.conf
echo.
echo 6. Save the file and restart Apache
echo 7. Apache should now show both HTTP (80) and HTTPS (443) ports
echo.
echo 8. Test access from your phone:
echo    - HTTP:  http://192.168.254.100/joyces/qr_scanner.php
echo    - HTTPS: https://192.168.254.100/joyces/qr_scanner.php
echo.
echo ========================================
echo SECURITY WARNING FOR MOBILE ACCESS:
echo ========================================
echo.
echo Mobile browsers will show "Not Secure" warnings because
echo we're using a self-signed certificate. You'll need to:
echo.
echo 1. Accept the security warning
echo 2. Click "Advanced" or "Proceed anyway"
echo 3. Add an exception for this site
echo.
echo This is NORMAL for local development!
echo.
pause 