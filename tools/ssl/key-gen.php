<?php
$config = array(
	"digest_alg" => "sha512",
	"private_key_bits" => 2048,
	"private_key_type" => OPENSSL_KEYTYPE_RSA,
);

$private_key = openssl_pkey_new($config);
openssl_pkey_export($private_key, $private_key_pem);
file_put_contents(filename: "key-priv.crt", data: $private_key_pem);
echo "PRIVATE KEY (saved to key-priv.crt):\n\n" . $private_key_pem . "\n\n";

// modulus
echo "MODULUS:\n\n" . bin2hex(openssl_pkey_get_details($private_key)["rsa"]["n"]) . "\n\n";

// public exponent
echo "PUBLIC EXPONENT:\n\n" . bin2hex(openssl_pkey_get_details($private_key)["rsa"]["e"]) . "\n\n";

// public key
$public_key_pem = openssl_pkey_get_details($private_key)['key'];
file_put_contents(filename: "key-pub.crt", data: $public_key_pem);
echo "PUBLIC KEY (saved to key-pub.crt):\n\n" . $public_key_pem . "\n\n";

exit;