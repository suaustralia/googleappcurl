<?php

namespace SUQLD;

class GoogleAppCurl
{
    private $client_id;
    private $client_secret;
    private $refresh_token;
    private $access_token; // This is retrieved from google via the refresh token

    public function __construct($client_id, $client_secret, $refresh_token)
    {
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;
        $this->refresh_token = $refresh_token;
        $this->refreshTokens();
    }

    private function refreshTokens()
    {
        // Take refresh token and get access token
        $result = json_decode($this->curlRequest(
            'https://accounts.google.com/o/oauth2/token',
            [
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'refresh_token' => $this->refresh_token,
                'grant_type' => 'refresh_token'
            ]
        ));

        // TODO check for errors
        $this->access_token = $result->access_token;
    }

    /**
     * https://developers.google.com/admin-sdk/directory/v1/reference/users/list
     * https://developers.google.com/admin-sdk/directory/v1/guides/search-users
     * @param array $searchFields
     * @return bool|\stdClass
     */
    public function findUser($searchFields = [])
    {
        $url = 'https://www.googleapis.com/admin/directory/v1/users';

        $query = [];
        foreach ($searchFields as $key => $value) {
            $query[] = $key . '=' .$value;
        }

        $params = [
            'customer' => 'my_customer',
            'query' => implode(',', $query)
        ];
        $fullUrl = $url . '?' . http_build_query($params);

        $result = json_decode($this->curlRequest($fullUrl, []));

        if (!isset($result->users)) {
            return false;
        }

        return $result->users[0];
    }

    /**
     * Checks if an email address belongs to a Google Apps account.
     * @param string $email The email address to check.
     * @return bool Returns true if the email belongs to a user, and false if it doesn't.
     */
    public function checkUserAlias($email)
    {
        $result = $this->findUser(['email' => $email]);

        // Email not found
        if ($result === false) {
            return false;
        }

        return true;
    }

    /**
     * Checks if an email address is a Google Group.
     * @param string $email The email address to check.
     * @return bool Returns true if the email is a group, and false if it isn't.
     */
    public function checkGroupAlias($email)
    {
        $result = json_decode($this->curlRequest(
            'https://www.googleapis.com/admin/directory/v1/groups/' . urlencode($email)
        ));

        // Group not found
        if (isset($result->error)) {
            return false;
        }

        return true;
    }

    /**
     * Checks if an email address belongs to a user or is a Google Group.
     * @param string $email The email address to check.
     * @return bool Returns true if the email belongs to a user or group, and false if it doesn't.
     */
    public function checkAlias($email)
    {
        if ($this->checkUserAlias($email)) {
            return true;
        }
        if ($this->checkGroupAlias($email)) {
            return true;
        }

        // Email does not belong to a user or a group
        return false;
    }

    private function curlRequest($url, array $request_data = [])
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        if ($this->access_token) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $this->access_token]);
        }
        if ($request_data) {
            $postfields = [];
            foreach ($request_data as $field => $value) {
                $postfields[] = "$field=" . urlencode($value);
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, implode($postfields, '&'));
        }

        $result = curl_exec($ch);

        curl_close($ch);
        return $result;
    }
}
