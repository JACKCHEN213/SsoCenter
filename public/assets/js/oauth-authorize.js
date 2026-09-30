'use strict';

/**
 * SSO 授权登录页交互逻辑
 *
 * 依赖：
 *  - jQuery（由 template/login.html 引入）
 *  - blueimp-md5（由 template/login.html 引入，提供 md5 函数）
 *  - ex.js（提供 messageEx、confirmEx）
 *  - window.__SSO_PARAMS__（由 oauth_authorize.html 模板注入）
 */
(function () {
    var rawParams = window.__SSO_PARAMS__ || {};
    // T2.2 新参数名优先，兼容旧参数名（与后端一致）
    var params = {
        APP_ID: rawParams.APP_ID || rawParams.client_id || '',
        state: rawParams.state || rawParams.state || '',
        callback: rawParams.callback || rawParams.redirect_uri || '',
        app_name: rawParams.app_name || '',
        is_logged_in: rawParams.is_logged_in || false
    };
    var ssoToken = localStorage.getItem('token') || '';

    // 兼容：管理后台旧登录流程将 token 以 base64 形式存储；SSO 登录直接存原始 JWT
    // 这里统一规范化：若 token 是 base64 包裹的 JWT，则尝试还原为原始 JWT 使用
    function normalizeToken(raw) {
        if (!raw) { return ''; }
        // 原始 JWT：三段 base64url 以 '.' 分隔
        if (/^[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+$/.test(raw)) {
            return raw;
        }
        // 否则尝试 base64 解码
        try {
            var decoded = atob(raw);
            if (/^[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+$/.test(decoded)) {
                return decoded;
            }
        } catch (e) {
            // ignore
        }
        return raw;
    }

    ssoToken = normalizeToken(ssoToken);

    /**
     * SSO 登录（AJAX）— 状态 A 提交
     */
    function ssoLogin(event) {
        event.preventDefault();
        var username = $('#username').val();
        var password = md5($('#password').val());

        $.ajax({
            type: 'POST',
            url: '/login/login',
            contentType: 'application/json;charset=utf-8',
            data: JSON.stringify({ username: username, password: password }),
            success: function (data) {
                if (data.code === 0) {
                    // 存原始 JWT（服务端返回的就是原始 JWT）
                    ssoToken = data.result;
                    localStorage.setItem('token', ssoToken);
                    // 同时设置 cookie 以便后续 authorizePage 服务端检测登录态
                    document.cookie = 'authorization=' + encodeURIComponent(ssoToken) + '; path=/; SameSite=Lax';
                    messageEx('登录成功', 'success', 500);
                    setTimeout(function () {
                        switchToConfirmView();
                    }, 400);
                } else {
                    messageEx(data.result || '登录失败', 'danger', 800);
                }
            },
            error: function () {
                messageEx('请求失败', 'danger', 800);
            }
        });
    }

    /**
     * 切换到授权确认视图（状态 B）
     */
    function switchToConfirmView() {
        $('#login-view').fadeOut(200, function () {
            $('#confirm-view').fadeIn(200);
        });
    }

    /**
     * 用户点击"允许" — POST /oauth/authorize/confirm
     */
    function confirmAuthorize() {
        if (!ssoToken) {
            messageEx('登录态丢失，请重新登录', 'danger', 800);
            return;
        }
        $.ajax({
            type: 'POST',
            url: '/oauth/authorize/confirm',
            contentType: 'application/json;charset=utf-8',
            headers: { Authorization: ssoToken },
            data: JSON.stringify({
                APP_ID: params.APP_ID,
                callback: params.callback,
                state: params.state
            }),
            success: function (data) {
                if (data.code === 0 && data.result && data.result.redirect_url) {
                    window.location.href = data.result.redirect_url;
                } else {
                    messageEx((data.result && typeof data.result === 'string') ? data.result : (data.message || '授权失败'), 'danger', 1000);
                }
            },
            error: function () {
                messageEx('请求失败', 'danger', 800);
            }
        });
    }

    /**
     * 用户点击"拒绝" — 直接跳转到 redirect_uri 带 error 参数
     */
    function denyAuthorize() {
        // T2.3 使用新参数名 callback / state
        var url = params.callback +
            '?error=access_denied' +
            '&error_description=' + encodeURIComponent('User denied the request') +
            '&state=' + encodeURIComponent(params.state || '');
        window.location.href = url;
    }

    /**
     * 切换账号：清除登录态并刷新页面
     */
    function logoutAndRelogin() {
        localStorage.removeItem('token');
        document.cookie = 'authorization=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
        window.location.reload();
    }

    // 暴露到全局，供模板 onclick 调用
    window.ssoLogin = ssoLogin;
    window.confirmAuthorize = confirmAuthorize;
    window.denyAuthorize = denyAuthorize;
    window.logoutAndRelogin = logoutAndRelogin;
})();
