<?php
/**
 * This class contains a few utility methods for working with Zepher results.
 *  - logout()
 *  - gerRoles()
 *  - validateAccess()
 *  - getEnv()
 *
 * Caching is experimental. The structure herein is ready once the API logic is complete.
 */

namespace Zepher\PHPClient;

use SQLite3;

class ZepherClient
{
    private $appKey;
    private $sqlite;
    private $jsEnvFile;
    private static $env;


    /**
     * If provided, a JSON array of the env will be created in the $jsEnvFile.
     * @param string $appKey Your app secret key.
     * @param string|null $jsEnvFile (i.e. zepher.js)
     */
    public function __construct(string $appKey, string $jsEnvFile = null)
    {
        $this->appKey = $appKey;
        $this->jsEnvFile = $jsEnvFile;

        $this->sqlite = new SQLite3('zepher.cache');
        $this->validateDbTable();
    }


    private function validateDbTable()
    {
        if (!$this->sqlite->querySingle("select `name` from sqlite_master where type='table' and `name` = 'cache'")) {
            $this->sqlite->exec("create table `cache` (`uri` text, `jwt` text, `response` text, primary key (`uri`, `jwt`))");
        }
    }


    public function get(string $uri, array $data = []): array
    {
        $uri = $data ? ($uri . '?' . http_build_query($data)) : $uri;

        $ch = curl_init($uri);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        // Validate credentials on the first request, then use the JWT for subsequent requests.

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . (!empty($_COOKIE['zepher-jwt']) ? $_COOKIE['zepher-jwt'] : $this->appKey)
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $errno = curl_errno($ch);

        if (is_resource($ch)) {
            curl_close($ch);
        }

        if (0 !== $errno) {
            throw new \RuntimeException($error, $errno);
        }

        return $this->processResponse($response, $uri);
    }


    public function post(string $uri): array
    {
        $ch = curl_init($uri);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($_POST));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        // Validate credentials on the first request, then use the JWT for subsequent requests.

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . (!empty($_COOKIE['zepher-jwt']) ? $_COOKIE['zepher-jwt'] : $this->appKey)
        ]);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $errno = curl_errno($ch);

        if (is_resource($ch)) {
            curl_close($ch);
        }

        if (0 !== $errno) {
            throw new \RuntimeException($error, $errno);
        }

        return $this->processResponse($response, $uri);
    }


    public function delete(string $uri): array
    {
        $ch = curl_init($uri);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        // Validate credentials on the first request, then use the JWT for subsequent requests.

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . (!empty($_COOKIE['zepher-jwt']) ? $_COOKIE['zepher-jwt'] : $this->appKey)
        ]);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $errno = curl_errno($ch);

        if (is_resource($ch)) {
            curl_close($ch);
        }

        if (0 !== $errno) {
            throw new \RuntimeException($error, $errno);
        }

        return $this->processResponse($response, $uri);
    }


    private function processResponse($response, $uri): array
    {
        $php = json_decode($response, true);

        if(!empty($php['error'])){
            if($php['error']['code'] == 410){
                throw new \RuntimeException($php['error']['message'], $php['error']['code']);
            }
        }

        self::$env = $php['env']??[];

        if (!empty($php['jwt'])) {

            $this->sqlite->exec("insert or replace into `cache` (`uri`, `jwt`, `response`) values ('{$uri}', '{$php['jwt']}', '{$response}')");

            setcookie('zepher-jwt', $php['jwt'], 0, "/");
            $_COOKIE['zepher-jwt'] = $php['jwt'];
        }

        if ($this->jsEnvFile) {
            file_put_contents($this->jsEnvFile, 'var zepher = JSON.parse(\'' . json_encode($php['env']) . '\');');
        }

        return $php ?? [];
    }


    /**
     * Destroys the current JWT forcing a login.
     * @return void
     */
    public static function logout()
    {
        \DeLoachTech\AppCore\setcookie('zepher-jwt', null, -1, '/');
        unset($_COOKIE['zepher-jwt']);
    }


    /**
     * Returns the current environment.
     * @return array
     */
    public static function getEnv(): array
    {
        return self::$env ?? [];
    }


    /**
     * Returns an array of available roles. Array keys are role ids and values are their titles.
     * @return array
     */
    public static function getRoles(): array
    {
        return self::$env['access']['roles'] ?? [];
    }


    /**
     * @param string $features A feature or comma delimited string of features (i.e. foo,bar,baz)
     * @param string|null $permissions A permission or comma delimited string of permissions (i.e. c,u)
     * @return bool
     */
    public static function validateAccess(string $features, string $permissions = '*'): bool
    {
        $permissions = str_replace([" ", ","], ["", "|"], $permissions);

        foreach (explode(',', $features) as $feature) {
            if ($feature == '*' || isset(self::$env['access']['features'][trim($feature, " ")])) {
                if ($permissions == '*') {
                    return true;
                }
                else {
                    return preg_match("/$permissions/i", self::$env['access']['features'][trim($feature, " ")]) === 1;
                }
            }
        }
        return false;
    }

}