<?php
/**
 * 加密和解密
 *
 * User: 张召
 * Date: 2022/06/10
 * Time: 17:30
 */
namespace Common\Libs;
use Illuminate\Support\Facades\File;


class Rsa{

    /**
     * 获取私钥信息
     * @return mixed
     */
    private static function getPrivateKey(){
        return File::get(app()->configPath('keys/login_ras_private.key'));
    }

    /**
     * 获取公钥信息
     * @return mixed
     */
    private static function getPublicKey(){
        return File::get(app()->configPath('keys/login_ras_public.key'));
    }

    /*-----------------------------  公钥加密, 私钥解密 --------------------------------------*/
    /*
     * RSA公钥加密
     * 使用私钥解密
     */
    public static function publicEncrypt($aString) {
        $pu_key = openssl_pkey_get_public(self::getPublicKey());//这个函数可用来判断公钥是否是可用的
        openssl_public_encrypt($aString, $encrypted, $pu_key);//公钥加密，私钥解密
        $encrypted = base64_encode($encrypted);//加密后的内容通常含有特殊字符，需要编码转换下，在网络间通过url传输时要注意base64编码是否是url安全的
        return $encrypted;
    }
    /*
     * RSA私钥解密
     * 有可能传过来的aString是经过base64加密的，则传来前需先base64_decode()解密
     * 返回未经base64加密的字符串
     */
    public static function privateDecrypt($aString) {
        $pr_key = openssl_pkey_get_private(self::getPrivateKey());//这个函数可用来判断私钥是否是可用的
        openssl_private_decrypt(base64_decode($aString), $decrypted, $pr_key);//公钥加密，私钥解密
        return $decrypted;
    }

    /*-----------------------------  私钥加密, 公钥解密 --------------------------------------*/
    /*
     * RSA私钥加密
     * 加密一个字符串，返回RSA加密后的内容
     * aString 需要加密的字符串
     * return encrypted rsa加密后的字符串
     */
    public static function privateEncrypt($aString) {
        //echo "------------",$aString,"====";
        $pr_key = openssl_pkey_get_private(self::getPrivateKey());//这个函数可用来判断私钥是否是可用的，可用返回资源id Resource id
        openssl_private_encrypt($aString, $encrypted, $pr_key);//私钥加密
        $encrypted = base64_encode($encrypted);//加密后的内容通常含有特殊字符，需要编码转换下，在网络间通过url传输时要注意base64编码是否是url安全的
        //echo "加密后:",$encrypted,"\n";
        return $encrypted;
    }
    /*
     * RSA公钥解密
     */
    public static function publicDecrypt($aString) {
        $pu_key = openssl_pkey_get_public(self::getPublicKey());//这个函数可用来判断公钥是否是可用的
        openssl_public_decrypt(base64_decode($aString), $decrypted, $pu_key);//公钥加密，私钥解密
        return $decrypted;
    }


}

