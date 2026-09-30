<?php
declare(strict_types=1);

// SCIM 用户同步端点路由（SM3 签名验证）
use app\controller\Scim;
use app\http\middleware\ValidateParams;
use app\http\middleware\VerifySignature;
use think\facade\Route;

// externalId 可能包含连字符（如 UUID），需放宽默认路由变量正则（默认 [\w\.]+ 不含 -）
$externalIdPattern = ['externalId' => '[^/]+'];

Route::group('api/scim/v2', function () use ($externalIdPattern) {
    // 创建账号（upsert 语义）
    Route::post('accounts', [Scim::class, 'createAccount'])
        ->middleware([VerifySignature::class, ValidateParams::class]);
    // 修改账号
    Route::put('accounts/:externalId', [Scim::class, 'updateAccount'])
        ->pattern($externalIdPattern)
        ->middleware([VerifySignature::class, ValidateParams::class]);
    // 删除账号（软删除）— 无 Body 参数，externalId 来自 URL，无需参数校验
    Route::delete('accounts/:externalId', [Scim::class, 'deleteAccount'])
        ->pattern($externalIdPattern)
        ->middleware([VerifySignature::class]);
});
