<?php
$config = array(
    "digest_alg" => "sha512",
    "private_key_bits" => 2048,
    "private_key_type" => OPENSSL_KEYTYPE_RSA,
);

$private_key = openssl_pkey_new($config);
openssl_pkey_export($private_key, $private_key_pem);
echo $private_key_pem . "\n\n";
// modulus
echo bin2hex(openssl_pkey_get_details($private_key)["rsa"]["n"]) . "\n\n";
// public exponent
echo bin2hex(openssl_pkey_get_details($private_key)["rsa"]["e"]) . "\n\n";
// public key
$public_key_pem = openssl_pkey_get_details($private_key)['key'];
echo $public_key_pem . "\n\n";
