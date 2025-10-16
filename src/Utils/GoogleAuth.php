<?php

namespace Utils;

class GoogleAuth
{
    public static function verifyGoogleToken($idToken)
    {
        try {
            // Validate input
            if (empty($idToken) || !is_string($idToken)) {
                return false;
            }

            // Google's token verification endpoint
            $url = "https://oauth2.googleapis.com/tokeninfo?id_token=" . urlencode($idToken);
            
            // Create context with timeout and error handling
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'method' => 'GET',
                    'header' => 'User-Agent: PHP-GoogleAuth/1.0'
                ]
            ]);
            
            $response = file_get_contents($url, false, $context);
            
            if ($response === false) {
                return false;
            }
            
            $data = json_decode($response, true); 
            if (!$data || isset($data['error'])) {
                return false;
            }
            
   
            
            // Verify required fields exist
            if (!isset($data['sub'], $data['email'], $data['aud'], $data['exp'])) {
                return false;
            }
            // Verify the audience (your Google Client ID)
            if ($data['aud'] !== $_ENV['GOOGLE_CLIENT_ID']) {
                return false;
            }
            
            // Verify token hasn't expired
            if ($data['exp'] < time()) {
                return false;
            }
            
            // Verify issuer
            $validIssuers = ['https://accounts.google.com', 'accounts.google.com'];
            if (!in_array($data['iss'], $validIssuers)) {
                return false;
            }
            
            return [
                'google_id' => $data['sub'],
                'email' => $data['email'],
                'name' => $data['name'] ?? '',
                'verified_email' => $data['email_verified'] ?? false,
                'picture' => $data['picture'] ?? null
            ];
            
        } catch (Exception $e) {
            error_log("GoogleAuth verification error: " . $e->getMessage());
            return false;
        }
    }
} 