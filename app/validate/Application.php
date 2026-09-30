<?php
declare(strict_types=1);

namespace app\validate;

use think\File;
use think\Validate;

/**
 * 应用管理验证器
 *
 * @package app\validate
 * @author  SSO Center
 * @since   1.0.0
 */
class Application extends Validate
{
    /**
     * OAuth 2.0 支持的授权模式
     */
    private const VALID_GRANT_TYPES = [
        'authorization_code',
        'implicit',
        'password',
        'client_credentials',
    ];

    protected function checkAppImage(File $image)
    {
        if (!$image->checkExt(config('common.IMAGE_EXT'))) {
            return 'file_data不是一个有效的的图片后缀名';
        }
        if (!$image->checkSize(config('common.MAX_IMAGE_SIZE'))) {
            return 'file_data大小最大为' . formatFileSize(config('common.MAX_IMAGE_SIZE'))
                . ', 当前为: ' . formatFileSize($image->getSize());
        }
        if (!is_dir(config('common.APP_IMAGE_PREFIX'))) {
            mkdir(config('common.APP_IMAGE_PREFIX'), 0777, true);
        }
        return true;
    }

    /**
     * 校验 allowed_grant_types：逗号分隔，每项必须为合法的授权模式
     *
     * @param string $value 待校验值
     * @return bool|string
     */
    protected function checkGrantTypes(string $value)
    {
        $types = array_filter(array_map('trim', explode(',', $value)));
        if (empty($types)) {
            return 'allowed_grant_types 不能为空';
        }
        foreach ($types as $type) {
            if (!in_array($type, self::VALID_GRANT_TYPES, true)) {
                return 'allowed_grant_types 包含不支持的授权模式: ' . $type;
            }
        }
        return true;
    }

    /**
     * 校验 push_apis 参数：JSON 数组，每项包含 action/url/method 等字段
     *
     * 增强版：校验 extra_headers、body_params、response_rules 结构化字段
     *
     * @param mixed $value 待校验值
     * @return bool|string
     */
    protected function checkPushApis($value)
    {
        if ($value === null || $value === '' || $value === []) {
            return true; // 可选参数，空值不校验
        }

        if (is_string($value)) {
            $apis = json_decode($value, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return 'push_apis 必须是合法的 JSON';
            }
        } else {
            $apis = $value;
        }

        if (!is_array($apis)) {
            return 'push_apis 必须是数组';
        }

        $validActions = ['create', 'update', 'delete'];
        $validMethods = ['POST', 'PUT', 'DELETE'];
        $validParamTypes = ['string', 'boolean', 'bool', 'int', 'integer'];
        $validRuleTypes = ['string', 'int', 'integer', 'boolean', 'bool'];

        foreach ($apis as $index => $api) {
            if (!is_array($api)) {
                return "push_apis[{$index}] 必须是对象";
            }

            // action 校验
            if (empty($api['action']) || !in_array($api['action'], $validActions, true)) {
                return "push_apis[{$index}].action 必须是 create/update/delete";
            }

            // url 校验
            if (empty($api['url'])) {
                return "push_apis[{$index}].url 不能为空";
            }
            if (!filter_var($api['url'], FILTER_VALIDATE_URL)) {
                return "push_apis[{$index}].url 不是合法的 URL";
            }

            // method 校验（可选，默认 POST）
            if (!empty($api['method']) && !in_array(strtoupper($api['method']), $validMethods, true)) {
                return "push_apis[{$index}].method 必须是 POST/PUT/DELETE";
            }

            // extra_headers 数组校验：每项必须有 name
            if (!empty($api['extra_headers'])) {
                $headers = is_string($api['extra_headers']) ? json_decode($api['extra_headers'], true) : $api['extra_headers'];
                if (!is_array($headers)) {
                    return "push_apis[{$index}].extra_headers 不是合法的数组";
                }
                foreach ($headers as $hIdx => $header) {
                    if (!is_array($header) || !isset($header['name']) || $header['name'] === '') {
                        return "push_apis[{$index}].extra_headers[{$hIdx}].name 不能为空";
                    }
                }
            }

            // body_params 数组校验：每项必须有 name，type 必须合法
            if (!empty($api['body_params'])) {
                $params = is_string($api['body_params']) ? json_decode($api['body_params'], true) : $api['body_params'];
                if (!is_array($params)) {
                    return "push_apis[{$index}].body_params 不是合法的数组";
                }
                foreach ($params as $pIdx => $param) {
                    if (!is_array($param) || !isset($param['name']) || $param['name'] === '') {
                        return "push_apis[{$index}].body_params[{$pIdx}].name 不能为空";
                    }
                    if (!empty($param['type']) && !in_array($param['type'], $validParamTypes, true)) {
                        return "push_apis[{$index}].body_params[{$pIdx}].type 必须是 string/boolean/int 之一";
                    }
                }
            }

            // response_rules 数组校验：每项必须有 field 和 type
            if (!empty($api['response_rules'])) {
                $rules = is_string($api['response_rules']) ? json_decode($api['response_rules'], true) : $api['response_rules'];
                if (!is_array($rules)) {
                    return "push_apis[{$index}].response_rules 不是合法的数组";
                }
                foreach ($rules as $rIdx => $rule) {
                    if (!is_array($rule) || !isset($rule['field']) || $rule['field'] === '') {
                        return "push_apis[{$index}].response_rules[{$rIdx}].field 不能为空";
                    }
                    if (!empty($rule['type']) && !in_array($rule['type'], $validRuleTypes, true)) {
                        return "push_apis[{$index}].response_rules[{$rIdx}].type 必须是 string/int/boolean 之一";
                    }
                }
            }
        }

        return true;
    }

    public function sceneAdd(): Application
    {
        return $this->only(['app_redirect_url', 'app_name', 'app_request_url', 'app_img_url', 'allowed_grant_types', 'scope', 'access_token_ttl', 'refresh_token_ttl', 'code_ttl', 'push_apis'])
            ->append('app_name', ['require'])
            ->append('app_request_url', ['require'])
            ->append('app_redirect_url', ['require'])
            ->append('allowed_grant_types', ['checkGrantTypes'])
            ->append('scope', ['max' => 255])
            ->append('access_token_ttl', ['integer', 'gt' => 0])
            ->append('refresh_token_ttl', ['integer', 'gt' => 0])
            ->append('code_ttl', ['integer', 'gt' => 0])
            ->append('push_apis', ['checkPushApis']);
    }

    public function sceneUploadImage(): Application
    {
        return $this->only(['file_data'])
            ->append('file_data', ['require', 'file', 'checkAppImage']);
    }

    public function sceneDeleteUploadedImage(): Application
    {
        return $this->only(['image_url'])
            ->append('image_url', ['require']);
    }

    public function sceneDelete(): Application
    {
        return $this->only(['id'])
            ->append('id', ['require']);
    }

    public function sceneUpdate(): Application
    {
        return $this->only(['id', 'app_redirect_url', 'app_name', 'app_request_url', 'app_img_url', 'allowed_grant_types', 'scope', 'access_token_ttl', 'refresh_token_ttl', 'code_ttl', 'push_apis'])
            ->append('id', ['require'])
            ->append('app_name', ['require'])
            ->append('app_request_url', ['require'])
            ->append('app_redirect_url', ['require'])
            ->append('allowed_grant_types', ['checkGrantTypes'])
            ->append('scope', ['max' => 255])
            ->append('access_token_ttl', ['integer', 'gt' => 0])
            ->append('refresh_token_ttl', ['integer', 'gt' => 0])
            ->append('code_ttl', ['integer', 'gt' => 0])
            ->append('push_apis', ['checkPushApis']);
    }

    public function sceneDetail(): Application
    {
        return $this->only(['id'])
            ->append('id', ['require']);
    }

    public function sceneResetSecret(): Application
    {
        return $this->only(['id'])
            ->append('id', ['require']);
    }

    public function sceneGetSecret(): Application
    {
        return $this->only(['id'])
            ->append('id', ['require']);
    }

    public function sceneDownloadSecret(): Application
    {
        return $this->only(['id'])
            ->append('id', ['require']);
    }

    public function sceneVisitApp(): Application
    {
        return $this->only(['id'])
            ->append('id', ['require']);
    }

    public function sceneGetPushApis(): Application
    {
        return $this->only(['site_id'])
            ->append('site_id', ['require']);
    }
}
