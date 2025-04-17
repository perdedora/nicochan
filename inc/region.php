<?php

class Regionblock
{
    private ?string $ip;
    private ?string $token;
    private const CHARSET = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    public function __construct(?string $ip = null, ?string $token = null)
    {

        $this->ip = self::trimField($ip);
        $this->token = self::trimField($token);
    }

	private static function trimField(?string $input): ?string
	{
		return $input ? trim($input) : null;
	}

    private function isTokenValid(): bool
    {
		return isset($this->token) && strlen($this->token) > 0 && strlen($this->token) <= 12;
    }

    private function isIPValid(): bool
    {
		return isset($this->ip) && filter_var($this->ip, FILTER_VALIDATE_IP);
    }

    // this is not to be secure, just a random ass string
    private static function generateToken(string $hash): string
    {

        $randomString = substr($hash, 0, 4);
        for ($i = 0; $i < 8; $i++) {
            $index = mt_rand(0, strlen(self::CHARSET) - 1);
            $randomString .= self::CHARSET[$index];
        }

        return $randomString;
    }

    public function addUser(): void
    {

		$this->validateIP();

        $hash = get_ip_hash($this->ip);

        if (!$this->token) {
            $this->token = self::generateToken($hash);
        }

        if (!$this->isTokenValid()) {
            error(_('Token must have, at least, 1 character to 12 charaters'));
        }

        $query = prepare('INSERT INTO ``whitelist_region`` (`ip`, `ip_hash`, `token`) VALUES (:ip, :ip_hash, :token)');
        $query->bindValue(':ip', $this->ip, PDO::PARAM_STR);
        $query->bindValue(':ip_hash', $hash, PDO::PARAM_STR);
        $query->bindValue(':token', $this->token, PDO::PARAM_STR);
        $query->execute() or error(db_error($query));

    }

    public function validateToken(): bool
    {

        if (!$this->isTokenValid()) {
            return false;
        }

        $query = prepare('SELECT 1 FROM ``whitelist_region`` WHERE `token` = :token');
        $query->bindValue(':token', $this->token, PDO::PARAM_STR);
        $query->execute() or error(db_error($query));

        if ($query->fetchColumn()) {
            return true;
        }

        return false;
    }

    public function revokeWhitelist(): void
    {

		$this->ip = ltrim($this->ip, '/');
		$this->validateIP();

        $query = prepare('DELETE FROM ``whitelist_region`` WHERE `ip` = :ip');
        $query->bindValue(':ip', $this->ip, PDO::PARAM_STR);
        $query->execute() or error(db_error($query));
    }

	private function validateIP(): void
	{
		if (!$this->isIPValid()) {
			error(_('Invalid IP'));
		}
	}

}
