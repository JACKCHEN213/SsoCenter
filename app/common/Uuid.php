<?php
declare(strict_types=1);

namespace app\common;

/**
 * UUID 生成工具
 *
 * @package app\common
 * @author  SSO Center
 * @since   1.0.0
 */
class Uuid
{
    /**
     * 生成 RFC 4122 UUID Version 4（随机）
     *
     * @return string 形如 550e8400-e29b-41d4-a716-446655440000 的 36 位 UUID
     */
    public static function uuid4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // version 4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // variant RFC 4122
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * 校验字符串是否为标准 UUID4 格式
     *
     * @param string $value
     * @return bool
     */
    public static function isValidUuid4(string $value): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value);
    }
}
