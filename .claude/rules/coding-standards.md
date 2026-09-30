# 编码规范

## PHP 编码规范（继承 PSR-12）

### 缩进与空格
- 使用 **4 个空格** 缩进（不使用 Tab）
- 每行最大长度 **120 字符**（软限制）
- 函数/方法参数过多时，换行后缩进 **4 个空格**

### 命名规范
- **类名**: 大驼峰（`UserController`、`LoginService`）
- **方法名**: 小驼峰（`getUserInfo`、`validateEmail`）
- **常量**: 全大写 + 下划线（`USER_NOT_FOUND`）
- **数据库字段**: 蛇形命名（`user_id`、`created_at`）

### 文件结构
```php
<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use think\facade\Db;

class UserController extends BaseController
{
    // 1. 属性
    protected $model;
    
    // 2. 构造函数
    public function __construct()
    {
        // ...
    }
    
    // 3. 公共方法
    public function actionLogin()
    {
        // ...
    }
    
    // 4. 私有方法
    private function validateParams($params)
    {
        // ...
    }
}
```

### 注释规范
- **类注释**: `@package`、`@author`、`@since`
```php
/**
 * 用户管理控制器
 *
 * @package app\controller
 * @author  Your Name
 * @since   1.0.0
 */
```

- **方法注释**: `@param`、`@return`、`@throws`
```php
/**
 * 用户登录
 *
 * @param array $params 登录参数（email, password）
 * @return array 登录结果
 * @throws BusinessException 当用户不存在或密码错误时
 */
```

### 严格类型
- 所有 PHP 文件**必须**包含 `declare(strict_types=1);`
- 函数参数和返回值**应该**声明类型

### 错误抑制
- ❌ **禁止使用** `@` 运算符（包括 `@file_get_contents`、`@mkdir` 等）
- ✅ 使用 `try-catch` 或条件判断处理错误

---

## JavaScript 编码规范（基于 ESLint 配置）

### 缩进与格式
- 使用 **4 个空格** 缩进（`indent: [2, 4]`）
- 语句结尾**必须**有分号（`semi: [2, 'always']`）
- 使用 **单引号**（`quotes: [1, 'single']`）
- 大括号风格：`1tbs`（`brace-style: [1, '1tbs']`）
- 逗号风格：末尾逗号（`comma-dangle: [2, 'never']`）

### 变量声明
- 使用 `const` 和 `let`，禁用 `var`（`no-var: 0`，虽然未强制但应优先使用 const/let）
- 变量**必须**先声明后使用（`no-use-before-define: 2`）

### 代码质量
- **禁止**使用 `alert`（`no-alert: 0`，但应避免）
- **禁止**使用 `eval`（`no-eval: 1`）
- **禁止**未使用的变量（`no-unused-vars: [1, { vars: 'all', args: 'after-used' }]`）
- **禁止**未定义的变量（`no-undef: 2`）
- **禁止**使用 `==` 和 `!=`（使用 `===` 和 `!==`，`eqeqeq: 2`）
- **必须**使用 `use strict`（`strict: 2`）

### 函数规范
- 函数参数**建议**不超过 3 个（`max-params: [0, 3]`）
- 回调函数**建议**使用箭头函数

### 注释规范
- `TODO`、`FIXME`、`XXX` 注释**应该**保留（`no-warning-comments: [1, { terms: ['todo', 'fixme', 'xxx'] }]`）

### 禁止事项
- ❌ 使用 `debugger`（`no-debugger: 2`）
- ❌ 使用 `with`（`no-with: 2`）
- ❌ 在循环中创建函数（`no-loop-func: 1`）
- ❌ 使用 `new` 但不赋值（`no-new: 1`）

### 全局变量（已定义）
以下全局变量可直接使用，无需声明：
`jQuery`、`$`、`navigateFn`、`UIToastr`、`LANG`、`CONF`、`bootbox`、`bootstrap`、`moment`、`Stepper`、`FullCalendar`、`swal`、`Dropzone`、`Tagify`、`toastr`、`CryptoJS`、`illegalStrCheck`、`illegalEmailCheck`、`illegalIpDomainCheck`、`illegalHttpIpDomainCheck`、`illegalPortCheck`、`illegalPhoneNumberCheck`、`resetFormValidate`、`getPopoverTipsContent`、`echarts`、`pAjaxRequest`、`UIIdleTimeout`、`Swiper`、`axios`、`axiosGet`、`axiosPost`、`axiosPut`、`axiosDelete`、`document`、`window`

---

## CSS/SCSS 编码规范（基于 Stylelint 配置）

### 缩进与格式
- 使用 **2 个空格** 缩进
- 选择器和属性各占一行
- 属性名和属性值冒号后**必须**有空格

### 属性排序（必须按此顺序）
1. **Display & Flow**: `display`、`visibility`、`float`、`clear`
2. **Positioning**: `position`、`top`、`right`、`bottom`、`left`、`z-index`、`transform`、`rotate`
3. **Flex**: `flex`、`flex-direction`、`flex-grow`、`flex-shrink`、`flex-basis`、`flex-wrap`、`justify-content`、`align-items`
4. **Dimensions**: `width`、`min-width`、`max-width`、`height`、`min-height`、`max-height`、`overflow`
5. **Margins, Padding, Borders, Outline**: `margin`、`padding`、`border`、`border-radius`、`outline` 等
6. **Typographic Styles**: `font`、`font-family`、`font-size`、`line-height`、`font-weight`、`text-align`、`color` 等
7. **Backgrounds**: `background`、`background-color`、`background-image`、`background-repeat`、`background-position`
8. **Opacity, Cursors, Generated Content, Transition**: `opacity`、`cursor`、`content`、`transition` 等

### 代码质量
- **禁止**无效十六进制颜色（`color-no-invalid-hex: true`）
- **禁止**空样式块（`block-no-empty: true`）
- **禁止**低优先级选择器覆盖高优先级（`no-descending-specificity: true`）
- **禁止**重复选择器（`no-duplicate-selectors: true`）
- **禁止**未知单位（`unit-no-unknown: true`）
- **禁止**未知属性（`property-no-unknown: true`）

### 禁止事项
- ❌ 使用 `!important` 在 keyframe 中（`keyframe-declaration-no-important: true`）
- ❌ 空注释（`comment-no-empty: true`）

