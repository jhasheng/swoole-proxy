<?php
/**
 * Created by PhpStorm.
 * User: krasen
 * Date: 8/21/2016
 * Time: 5:54 PM
 */

$crt = file_get_contents(__DIR__ . '/../cert/ca.crt');
$key = file_get_contents(__DIR__ . '/../cert/ca.key');

$reqKey = openssl_pkey_new();

if (openssl_pkey_export($reqKey, $outKey)) {
    $dn = [
        "countryName"            => "CN",
        "stateOrProvinceName"    => "HuBei",
        "organizationName"       => "MITM",
        "organizationalUnitName" => "jhasheng@hotmail.com",
        "commonName"             => "www.baidu.com"
    ];

    $reqCsr = openssl_csr_new($dn, $reqKey);

    $reqCrt = openssl_csr_sign($reqCsr, $crt, $key, 365);

    if (openssl_x509_export($reqCrt, $outCrt)) {
        file_put_contents(__DIR__ . "/../cert/server.crt", $outCrt);
        file_put_contents(__DIR__ . "/../cert/server.key", $outKey);
    }
}