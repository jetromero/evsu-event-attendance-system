<?php

namespace App;

class SupabaseClient
{
    private $url;
    private $key;
    private $headers;

    public function __construct($url, $key)
    {
        $this->url = rtrim($url, '/');
        $this->key = $key;
        $this->headers = [
            'apikey: ' . $key,
            'Authorization: Bearer ' . $key,
            'Content-Type: application/json',
            'Prefer: return=representation'
        ];
    }

    /**
     * Execute a cURL request
     */
    private function makeRequest($method, $endpoint, $data = null)
    {
        $url = $this->url . '/rest/v1/' . $endpoint;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        switch (strtoupper($method)) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if ($data) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                if ($data) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
            case 'PATCH':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
                if ($data) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception('cURL Error: ' . $error);
        }

        $decodedResponse = json_decode($response, true);

        if ($httpCode >= 400) {
            $errorMessage = isset($decodedResponse['message']) ? $decodedResponse['message'] : 'HTTP Error ' . $httpCode;
            throw new \Exception($errorMessage);
        }

        return $decodedResponse;
    }

    /**
     * Select data from a table
     */
    public function select($table, $columns = '*', $conditions = [])
    {
        // Properly encode the columns parameter to handle spaces
        $encodedColumns = str_replace(' ', '', $columns); // Remove spaces from column names
        $endpoint = $table . '?select=' . urlencode($encodedColumns);

        foreach ($conditions as $key => $value) {
            $endpoint .= '&' . urlencode($key) . '=eq.' . urlencode($value);
        }

        return $this->makeRequest('GET', $endpoint);
    }

    /**
     * Insert data into a table
     */
    public function insert($table, $data)
    {
        return $this->makeRequest('POST', $table, $data);
    }

    /**
     * Update data in a table
     */
    public function update($table, $data, $conditions = [])
    {
        $endpoint = $table;

        if (!empty($conditions)) {
            $endpoint .= '?';
            $conditionParts = [];
            foreach ($conditions as $key => $value) {
                $conditionParts[] = $key . '=eq.' . urlencode($value);
            }
            $endpoint .= implode('&', $conditionParts);
        }

        return $this->makeRequest('PATCH', $endpoint, $data);
    }

    /**
     * Delete data from a table
     */
    public function delete($table, $conditions = [])
    {
        $endpoint = $table;

        if (!empty($conditions)) {
            $endpoint .= '?';
            $conditionParts = [];
            foreach ($conditions as $key => $value) {
                $conditionParts[] = $key . '=eq.' . urlencode($value);
            }
            $endpoint .= implode('&', $conditionParts);
        }

        return $this->makeRequest('DELETE', $endpoint);
    }

    /**
     * Authenticate user with email and password
     */
    public function signInWithPassword($email, $password)
    {
        $url = $this->url . '/auth/v1/token?grant_type=password';

        $data = [
            'email' => $email,
            'password' => $password
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . $this->key,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decodedResponse = json_decode($response, true);

        if ($httpCode >= 400) {
            throw new \Exception($decodedResponse['error_description'] ?? 'Authentication failed');
        }

        return $decodedResponse;
    }

    /**
     * Register a new user
     */
    public function signUp($email, $password, $userData = [])
    {
        $url = $this->url . '/auth/v1/signup';

        $data = [
            'email' => $email,
            'password' => $password,
            'data' => $userData
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . $this->key,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decodedResponse = json_decode($response, true);

        if ($httpCode >= 400) {
            throw new \Exception($decodedResponse['error_description'] ?? 'Registration failed');
        }

        return $decodedResponse;
    }

    /**
     * Get user by JWT token
     */
    public function getUser($token)
    {
        $url = $this->url . '/auth/v1/user';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . $this->key,
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decodedResponse = json_decode($response, true);

        if ($httpCode >= 400) {
            throw new \Exception($decodedResponse['error_description'] ?? 'Failed to get user');
        }

        return $decodedResponse;
    }

    /**
     * Update user password
     */
    public function updateUserPassword($token, $newPassword)
    {
        $url = $this->url . '/auth/v1/user';

        $data = [
            'password' => $newPassword
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . $this->key,
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decodedResponse = json_decode($response, true);

        if ($httpCode >= 400) {
            throw new \Exception($decodedResponse['error_description'] ?? 'Failed to update password');
        }

        return $decodedResponse;
    }
}
