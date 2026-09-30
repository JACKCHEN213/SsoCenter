<?php
declare(strict_types=1);

namespace app\service;

use OneSm\Sm3;

/**
 * SM3 签名服务
 *
 * 自动计算 7 个签名 Header，用于推送到第三方系统
 * 参照 extend/账号同步说明.md 第 1.2 节实现
 *
 * @package app\service
 * @author  SSO Center
 * @since   1.0.0
 */
class SignatureService
{
    private Sm3 $sm3;

    public function __construct()
    {
        $this->sm3 = new Sm3();
    }

    /**
     * 计算请求内容的 MD5 值（32 位小写十六进制）
     *
     * POST/PUT：md5(request body)
     * DELETE：md5(urlPath + query)
     *
     * @param string $content 内容（body 或 urlPath+query）
     * @return string 32 位小写十六进制 MD5
     */
    public function contentMd5(string $content): string
    {
        return md5($content);
    }

    /**
     * SM3-HMAC 签名
     *
     * @param string $data 待签名数据
     * @param string $key 密钥（appkey）
     * @return string 32 位小写十六进制 SM3-HMAC 签名
     */
    public function sm3Hmac(string $data, string $key): string
    {
        $blockSize = 64; // SM3 block size in bytes

        // 如果密钥长度超过 block size，先做 hash
        if (strlen($key) > $blockSize) {
            $key = hex2bin($this->sm3->sign($key));
        }

        // 密钥不足 block size 时补零
        $key = str_pad($key, $blockSize, "\0");

        $ipad = str_repeat("\x36", $blockSize);
        $opad = str_repeat("\x5c", $blockSize);

        // inner hash: SM3((key ⊕ ipad) || data)
        $innerHash = hex2bin($this->sm3->sign($this->xorString($key, $ipad) . $data));

        // outer hash: SM3((key ⊕ opad) || innerHash)
        $outerHash = $this->sm3->sign($this->xorString($key, $opad) . $innerHash);

        return $outerHash;
    }

    /**
     * 计算完整签名 Header 数组
     *
     * @param string $body 请求体（DELETE 时传空字符串）
     * @param string $urlPath URL 路径+query（DELETE 时使用）
     * @param string $method HTTP 方法（POST/PUT/DELETE）
     * @param string $appid 第三方分配的 appid
     * @param string $appkey 第三方分配的 appkey
     * @return array 完整签名 Header 数组
     */
    public function sign(string $body, string $urlPath, string $method, string $appid, string $appkey): array
    {
        // 步骤 1：计算 Content-MD5-HEX
        $upperMethod = strtoupper($method);
        if (in_array($upperMethod, ['GET', 'DELETE'])) {
            $contentMd5 = $this->contentMd5($urlPath);
        } else {
            $contentMd5 = $this->contentMd5($body);
        }

        // 步骤 2：构造 7 个字段
        $timestamp = (string)time();
        $nonce = $this->generateNonce();

        $fields = [
            'content-md5-hex' => $contentMd5,
            'content-type' => 'application/json;charset=UTF-8',
            'method' => $upperMethod,
            'x-trust-appid' => $appid,
            'x-trust-nonce' => $nonce,
            'x-trust-signature-version' => '2.0',
            'x-trust-timestamp' => $timestamp,
        ];

        // 步骤 3：按字段名字母排序，用 & 拼接 → V
        ksort($fields);
        $parts = [];
        foreach ($fields as $k => $v) {
            $parts[] = $k . '=' . $v;
        }
        $v = implode('&', $parts);

        // 步骤 4：SM3-HMAC(K=appkey, V=V) → 签名
        $signature = $this->sm3Hmac($v, $appkey);

        // 步骤 5：返回 Header 数组
        return [
            'Content-Type' => 'application/json;charset=UTF-8',
            'X-trust-signature-version' => '2.0',
            'X-trust-appid' => $appid,
            'X-trust-timestamp' => $timestamp,
            'X-trust-nonce' => $nonce,
            'Content-MD5-HEX' => $contentMd5,
            'X-trust-signature' => $signature,
        ];
    }

    /**
     * 生成 16 位随机字符串（nonce）
     *
     * @return string 16 位随机字符串
     */
    private function generateNonce(): string
    {
        return substr(bin2hex(random_bytes(8)), 0, 16);
    }

    /**
     * 两个等长字符串按位异或
     *
     * @param string $a 字符串 A
     * @param string $b 字符串 B
     * @return string 异或结果
     */
    private function xorString(string $a, string $b): string
    {
        $result = '';
        $len = strlen($a);
        for ($i = 0; $i < $len; $i++) {
            $result .= chr(ord($a[$i]) ^ ord($b[$i]));
        }
        return $result;
    }
}
