<?php
declare(strict_types=1);

namespace app\service;

/**
 * 多规则验证结果对象
 *
 * @package app\service
 * @author  SSO Center
 * @since   1.0.0
 */
class ValidationResult
{
    public bool $isSuccess;
    public ?string $failedRule;

    private function __construct(bool $success, ?string $failedRule = null)
    {
        $this->isSuccess = $success;
        $this->failedRule = $failedRule;
    }

    public static function success(): self
    {
        return new self(true);
    }

    public static function failed(string $failedRule): self
    {
        return new self(false, $failedRule);
    }

    public function toArray(): array
    {
        return [
            'is_success' => $this->isSuccess,
            'failed_rule' => $this->failedRule,
        ];
    }
}
