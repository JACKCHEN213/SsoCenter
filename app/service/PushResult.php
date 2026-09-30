<?php
declare(strict_types=1);

namespace app\service;

/**
 * 推送结果值对象
 *
 * @package app\service
 * @author  SSO Center
 * @since   1.0.0
 */
class PushResult
{
    public bool $isSuccess;
    public bool $notConfigured;
    public ?string $errorMessage;
    public ?string $externalId;
    public ?string $failedRule;

    /** @var array|null 完整的 HTTP 请求数据（URL/method/headers/body） */
    public ?array $httpRequestData;

    /** @var array|null 完整的 HTTP 响应数据（status_code/headers/body） */
    public ?array $httpResponseData;

    /** @var int|null push_log 记录 ID */
    public ?int $pushLogId;

    private function __construct(
        bool $success,
        bool $notConfigured = false,
        ?string $error = null,
        ?string $externalId = null,
        ?string $failedRule = null,
        ?array $httpRequestData = null,
        ?array $httpResponseData = null,
        ?int $pushLogId = null
    ) {
        $this->isSuccess = $success;
        $this->notConfigured = $notConfigured;
        $this->errorMessage = $error;
        $this->externalId = $externalId;
        $this->failedRule = $failedRule;
        $this->httpRequestData = $httpRequestData;
        $this->httpResponseData = $httpResponseData;
        $this->pushLogId = $pushLogId;
    }

    public static function success(
        ?string $externalId = null,
        ?array $httpRequestData = null,
        ?array $httpResponseData = null,
        ?int $pushLogId = null
    ): self {
        return new self(true, false, null, $externalId, null, $httpRequestData, $httpResponseData, $pushLogId);
    }

    public static function failed(
        string $error,
        ?string $failedRule = null,
        ?array $httpRequestData = null,
        ?array $httpResponseData = null,
        ?int $pushLogId = null
    ): self {
        return new self(false, false, $error, null, $failedRule, $httpRequestData, $httpResponseData, $pushLogId);
    }

    public static function notConfigured(): self
    {
        return new self(false, true, '该应用未配置推送接口');
    }

    public function toArray(): array
    {
        return [
            'is_success' => $this->isSuccess,
            'not_configured' => $this->notConfigured,
            'error_message' => $this->errorMessage,
            'external_id' => $this->externalId,
            'failed_rule' => $this->failedRule,
        ];
    }
}
