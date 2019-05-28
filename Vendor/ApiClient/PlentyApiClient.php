<?php

namespace PaulRecommend\Vendor\ApiClient;

/**
 * Class PlentyApiClient
 */
class PlentyApiClient
{

    const PATH_LOGIN = "rest/login";
    const METHOD_GET = "GET";
    const METHOD_POST = "POST";
    const METHOD_PUT = "PUT";
    const METHOD_DELETE = "DELETE";

    private $client;
    private $token;
    private $tokenFile;
    private $user;
    private $password;
    private $url;

    public function __construct($user, $password, $url)
    {

        $this->setUser($user);
        $this->setPassword($password);
        $this->setUrl($url);

        $this->login();
    }

    public function login()
    {
        if ($this->checkTokenValid()) {
            //Login nicht notwendig, da token gültig
        } else {
            $params = array(
                'country' => 'Germany',
                'city' => 'Hildesheim',
                'username' => $this->getUser(),
                'password' => $this->getPassword()
            );

            $headers = array(
                'Authorization: Bearer ' . $this->getTokenFromFile(),
                'Content-Type: application/json',
            );

            $curl = curl_init();
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($params));

            curl_setopt($curl, CURLOPT_URL, $this->url . '/rest/login');
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);

            $response = curl_exec($curl);
            $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);


            if ($code == 200) {

                // Login erfolgreich
                $this->saveTokenFile(json_decode($response, TRUE));

            } elseif ($code == 401) {

                //Token in Datei speichern wenn Login nicht möglich
                $this->saveTokenFile($response);
                $this->login();

            } else {
                echo 'error ' . $code;
            }
        }
    }

    private function checkTokenValid()
    {

        $now = date('Y-m-d H:i:s', time());
        $tokenFile = $this->getTokenFromFile();

        if ($tokenFile['valid_till'] > $now) {
            return true;
        } else {
            return false;
        }
    }

    private function getTokenFromFile()
    {
        $this->setToken(unserialize(file_get_contents(__DIR__ . '/token.php')));
        return $this->token;
    }

    /**
     * @return mixed
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param mixed $user
     */
    public function setUser($user)
    {
        $this->user = $user;
    }

    /**
     * @return mixed
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @param mixed $password
     */
    public function setPassword($password)
    {
        $this->password = $password;
    }

    private function saveTokenFile($token)
    {
        $timestamp = time() + $token['expires_in'];

        $date = [
            "valid_till" => date('Y-m-d H:i:s', $timestamp),
        ];

        $tokenArray = array_merge($token, $date);
        file_put_contents(__DIR__ . '/token.php', serialize($tokenArray));
    }

    /**
     * @return mixed
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * @return mixed
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * @param mixed $token
     */
    public function setToken($token)
    {
        $this->token = $token;
    }

    /**
     * @return mixed
     */
    public function getTokenFile()
    {
        return $this->tokenFile;
    }

    /**
     * @return mixed
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @param mixed $url
     */
    public function setUrl($url)
    {
        $this->url = $url;
    }

    public function call($method, $path, $params = [])
    {
            if ($this->checkTokenValid()) {

                $params = array_merge($params, [
                    "Authorization" => "Bearer " . $this->getTokenFromFile()['access_token'],
                    "cache-control: no-cache"
                ]);

                try {
                    $curl = curl_init();

                    switch ($method) {
                        case 'GET':
                            $this->url .= $path . '?' . http_build_query($params);
                            break;
                        case 'POST':
                            curl_setopt($curl, CURLOPT_POST, true);
                            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($params));
                            break;
                    }

                    curl_setopt_array($curl, array(
                        CURLOPT_URL => $this->url,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => "",
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 30,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => $method,
                        CURLOPT_HTTPHEADER => array(
                            "authorization: Bearer " . $this->getTokenFromFile()['access_token'],
                            "Cache-Control: no-cache"
                        ),
                    ));

                    $response = curl_exec($curl);
                    //var_dump($this->url);

                    $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                    curl_close($curl);

                    if ($code == 200) {
                        return $response;
                    } elseif ($code == 401) {
                        return false;
                    } else {
                        echo 'error ' . $code;
                    }
                } catch (\Exception $e) {
                }
            } else {
                $this->login();
            }


        return false;
    }
}