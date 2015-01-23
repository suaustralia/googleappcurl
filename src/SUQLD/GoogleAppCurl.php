<?php

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
		$this->refresh_tokens();
	}

	private function refresh_tokens()
	{
		// Take refresh token and get access token
		$result = json_decode($this->curlRequest('https://accounts.google.com/o/oauth2/token',
			[
				'client_id' => $this->client_id,
				'client_secret' => $this->client_secret,
				'refresh_token' => $this->refresh_token,
				'grant_type' => 'refresh_token'
			]));

		// TODO check for errors

		$this->access_token = $result->access_token;

	}

	public function checkUserAlias($email)
	{
		$domain = explode('@', $email)[1];
		$result = json_decode($this->curlRequest(
			'https://www.googleapis.com/admin/directory/v1/users?domain=' . urlencode($domain) . '&query=' . urlencode("email=$email"),
			[]));

		// TODO check for errors
		if (isset($result->users)) {
			return true;
		}
		//echo "User $email not found";
		return false;

	}

	public function checkGroupAlias($email)
	{
		$result = json_decode($this->curlRequest(
			'https://www.googleapis.com/admin/directory/v1/groups/' . urlencode("$email"),
			[]));

		if (isset($result->error)) {
			//echo "Group $email not found";
			return false;
		}
		return true;
	}

	public function checkAlias($email)
	{
		if ($this->checkUserAlias($email)) {
			echo "$email is a user";
			return true;
		}
		if ($this->checkGroupAlias($email)) {
			echo "$email is a group";
			return true;
		}

		echo "$email is not a user or a group";
		return false;
	}

	private function curlRequest($url, array $request_data)
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
