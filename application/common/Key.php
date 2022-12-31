<?php

namespace app\common;

use JWT;

class Key
{
    /**
     * @param $data
     * @return array
     */
    public function setToken($data): array
    {
        $new_key_pair = openssl_pkey_new(array(
            "private_key_bits" => 2048,
            "private_key_type" => OPENSSL_KEYTYPE_RSA,
        ));
        openssl_pkey_export($new_key_pair, $private_key_pem);

        $details = openssl_pkey_get_details($new_key_pair);
        $public_key_pem = $details['key'];
        $token = JWT::encode($data, $private_key_pem, 'RS256');
        return ['token' => $token, 'pub' => $public_key_pem];
    }

}
