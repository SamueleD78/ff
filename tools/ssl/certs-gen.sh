#!/usr/bin/sh
CANAME=myCA # Use a name for the CA files
SITENAME=mysite.app # Use your own domain name
IP=127.0.0.1 # Use the IP you want to bind with the certificates

rm -f $CANAME.* $SITENAME.*

echo "######################"
echo "# Create CA certs"

openssl genrsa -des3 -out ${CANAME}.key 4096
openssl req -new -x509 -days 3650 -key ${CANAME}.key -out ${CANAME}.crt

echo "######################"
echo "# Create CA-signed certs"

echo "# Generate a private key"
openssl genrsa -out $SITENAME.key 2048

echo "# Create a certificate-signing request"
openssl req -new -key $SITENAME.key -out $SITENAME.csr

echo "# Create a config file for the extensions"
>$SITENAME.ext cat <<-EOF
authorityKeyIdentifier=keyid,issuer
basicConstraints=CA:FALSE
keyUsage = digitalSignature, nonRepudiation, keyEncipherment, dataEncipherment
subjectAltName = @alt_names
[alt_names]
DNS.1 = $SITENAME
DNS.2 = *.$SITENAME
IP.1 = $IP
EOF

echo "# Create the signed certificate"
openssl x509 -req -in ${SITENAME}.csr -CA ${CANAME}.crt -CAkey ${CANAME}.key -CAcreateserial \
-out ${SITENAME}.crt -days 825 -sha256 -extfile ${SITENAME}.ext
