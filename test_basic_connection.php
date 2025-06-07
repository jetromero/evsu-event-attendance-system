<?php
// Simple connection test for dual-sync setup
require_once 'supabase_config.php';

echo "<h2>🔍 Basic Connection Test</h2>";
echo "<style>body{font-family:Arial;margin:20px;} .success{color:green;} .error{color:red;} .warning{color:orange;} .info{color:blue;}</style>";

echo "<h3>Configuration Check:</h3>";
echo "<div class='info'>Primary URL: " . htmlspecialchars($supabase_url) . "</div>";
echo "<div class='info'>Primary Key Type: " . (strpos($supabase_key, 'service_role') !== false ? 'SERVICE_ROLE ✅' : 'ANON ⚠️') . "</div>";
echo "<div class='info'>Secondary URL: " . htmlspecialchars($supabase_secondary_url) . "</div>";
echo "<div class='info'>Secondary Key Type: " . (strpos($supabase_secondary_key, 'service_role') !== false ? 'SERVICE_ROLE ✅' : 'ANON ⚠️') . "</div>";
echo "<div class='info'>Dual-Sync: " . ($enable_dual_sync ? 'ENABLED ✅' : 'DISABLED ❌') . "</div>";

echo "<hr>";

// Test Primary Database
echo "<h3>Primary Database Test:</h3>";
try {
    $supabase = getSupabaseClient();
    echo "<div class='info'>Testing connection...</div>";

    // Try to select from users table
    $result = $supabase->select('users', 'id, email', [], 'id DESC', 1);

    if (is_array($result)) {
        echo "<div class='success'>✅ Primary database connection: SUCCESS</div>";

        // Get all users for count
        $allUsers = $supabase->select('users', 'id');
        $count = is_array($allUsers) ? count($allUsers) : 0;
        echo "<div class='info'>📊 Users count: $count</div>";

        if (!empty($result)) {
            $sample = $result[0];
            echo "<div class='info'>📋 Table fields: " . implode(', ', array_keys($sample)) . "</div>";
            echo "<div class='info'>🔢 Sample ID: " . $sample['id'] . " (type: " . gettype($sample['id']) . ")</div>";
        }
    } else {
        echo "<div class='error'>❌ Primary database connection failed: " . json_encode($result) . "</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ Primary database error: " . htmlspecialchars($e->getMessage()) . "</div>";
    if (strpos($e->getMessage(), 'relation "public.users" does not exist') !== false) {
        echo "<div class='warning'>⚠️ Users table doesn't exist. You need to run the SQL script first!</div>";
    }
}

echo "<hr>";

// Test Secondary Database
echo "<h3>Secondary Database Test:</h3>";
try {
    $secondarySupabase = getSecondarySupabaseClient();
    echo "<div class='info'>Testing connection...</div>";

    // Try to select from users table
    $result = $secondarySupabase->select('users', 'id, email', [], 'id DESC', 1);

    if (is_array($result)) {
        echo "<div class='success'>✅ Secondary database connection: SUCCESS</div>";

        // Get all users for count
        $allUsers = $secondarySupabase->select('users', 'id');
        $count = is_array($allUsers) ? count($allUsers) : 0;
        echo "<div class='info'>📊 Users count: $count</div>";

        if (!empty($result)) {
            $sample = $result[0];
            echo "<div class='info'>📋 Table fields: " . implode(', ', array_keys($sample)) . "</div>";
            echo "<div class='info'>🔢 Sample ID: " . $sample['id'] . " (type: " . gettype($sample['id']) . ")</div>";
        }
    } else {
        echo "<div class='error'>❌ Secondary database connection failed: " . json_encode($result) . "</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ Secondary database error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<hr>";

// Test Simple Insert
echo "<h3>Simple Insert Test:</h3>";
$testEmail = 'connection-test-' . uniqid() . '@test.com';
$testData = [
    'email' => $testEmail,
    'password' => password_hash('testpass123', PASSWORD_DEFAULT),
    'first_name' => 'Connection',
    'last_name' => 'Test',
    'course' => 'Test Course',
    'year_level' => '1st Year',
    'section' => 'A',
    'role' => 'student'
];

echo "<div class='info'>Testing with email: $testEmail</div>";

try {
    // Test primary insert
    echo "<div class='info'>Inserting into primary database...</div>";
    $supabase = getSupabaseClient();
    $primaryResult = $supabase->insert('users', $testData);

    if ($primaryResult) {
        echo "<div class='success'>✅ Primary insert: SUCCESS</div>";

        // Test secondary insert if dual-sync enabled
        if ($enable_dual_sync) {
            echo "<div class='info'>Inserting into secondary database...</div>";
            $secondarySupabase = getSecondarySupabaseClient();
            $secondaryResult = $secondarySupabase->insert('users', $testData);

            if ($secondaryResult) {
                echo "<div class='success'>✅ Secondary insert: SUCCESS</div>";
                echo "<div class='success'>🎉 DUAL-SYNC IS WORKING!</div>";
            } else {
                echo "<div class='error'>❌ Secondary insert: FAILED</div>";
            }

            // Cleanup
            echo "<div class='info'>Cleaning up test data...</div>";
            $supabase->delete('users', ['email' => $testEmail]);
            $secondarySupabase->delete('users', ['email' => $testEmail]);
        } else {
            echo "<div class='warning'>⚠️ Dual-sync is disabled, skipping secondary test</div>";
            // Cleanup primary only
            $supabase->delete('users', ['email' => $testEmail]);
        }
    } else {
        echo "<div class='error'>❌ Primary insert: FAILED</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ Insert test error: " . htmlspecialchars($e->getMessage()) . "</div>";

    // Try to clean up in case of error
    try {
        if (isset($supabase)) {
            $supabase->delete('users', ['email' => $testEmail]);
        }
        if (isset($secondarySupabase)) {
            $secondarySupabase->delete('users', ['email' => $testEmail]);
        }
    } catch (Exception $cleanupError) {
        // Ignore cleanup errors
    }
}

echo "<hr>";
echo "<h3>Next Steps:</h3>";
echo "<ul>";
echo "<li>If you see errors about users table not existing, run the SQL script first</li>";
echo "<li>If connections fail, check your Supabase URLs and keys</li>";
echo "<li>If primary works but secondary fails, check secondary project setup</li>";
echo "<li>Once this test passes, run the full debug script</li>";
echo "</ul>";
