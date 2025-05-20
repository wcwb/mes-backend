---
name: Laravel 后端开发规范（MES 系统）
description: 本项目为 Laravel 12 + Jetstream + Spatie 权限系统的后端 API 项目，配合前端 Nuxt 3 实现服装 MES 系统。
globs:
  - app/**/*.php
  - routes/**/*.php
  - resources/lang/**/*.php
  - config/**/*.php
alwaysApply: true
---

## 📁 项目结构约定 [structure]
- Laravel 12 默认不再生成 app/Http/Kernel.php；
  Cursor AI 生成中间件时请改用 RouteServiceProvider 注册，或通过 bootstrap/app.php

- Controllers 路径按模块划分，例如：
  ```php
  // 正确的控制器路径
  app/Http/Controllers/Orders/OrdersController.php
  app/Http/Controllers/Products/ProductsController.php
  ```

- 模型统一放在 app/Models；
- 权限统一命名为：<模块>.<动作>，如 orders.view；
- 使用中间件自动注入 Spatie 的 team_id；
- 所有响应使用 Laravel Resource。

## 🔐 权限控制规范 [permissions]

- 控制器使用 `$user->can(...)` 或 `abort_unless(...)` 进行授权；
  ```php
  // 正确的授权方式
  public function show(Order $order)
  {
      abort_unless(auth()->user()->can('orders.view'), 403, '没有查看订单权限');
      return new OrderResource($order);
  }
  ```
- 前端权限数组从 `$page.props.auth.user.permissions` 获取。

## 🧪 路由规范 [routes]

- 所有 API 路由放入 routes/api.php；
- 路由命名统一为 api.<模块>.<方法>。
  ```php
  // 正确的路由定义
  Route::get('/orders', [OrdersController::class, 'index'])->name('api.orders.index');
  Route::post('/orders', [OrdersController::class, 'store'])->name('api.orders.store');
  ```

## 🧱 数据规范 [database]

- 表命名使用 snake_case；
- 所有表含 timestamps 和 deleted_at；
- 所有表默认包含 team_id 实现权限隔离。
- 以下表可不包含 team_id：
  - `roles`, `permissions`, `model_has_roles`, `model_has_permissions`
  - `config`, `settings`（全局配置）
  - `base_units`, `dictionaries`, `country_codes` 等基础字典表
  - `logs`, `system_audit_logs`（系统日志类全局表）
  - 用户信息表 users（通常属于多个团队，可不加 team_id）
- 外键约束应定义清晰，主键使用 bigint 自增 ID。

  ```php
  // 正确的迁移文件示例
  Schema::create('orders', function (Blueprint $table) {
      $table->id();
      $table->foreignId('team_id')->constrained();
      $table->string('order_number');
      // 其他字段...
      $table->timestamps();
      $table->softDeletes();
  });
  ```

## 🧠 Laravel 编程风格 [coding-style]

- 遵循 PSR-12 和 Laravel 官方命名规范；
- 控制器精简，业务逻辑放入 Service 类；
  ```php
  // 正确的控制器结构
  public function store(StoreOrderRequest $request)
  {
      $order = $this->orderService->createOrder($request->validated());
      return new OrderResource($order);
  }
  ```
- 命名采用小驼峰风格：getUserOrders, syncMaterials；
- 控制器返回统一格式 JSON 响应（使用 Resource）；
- 避免直接返回模型实例；
- 所有模型需支持 factory 用于测试。

## 🚨 错误响应规范（统一 API 格式）[error-handling]

所有错误返回统一 JSON 格式如下：

```json
{
  "message": "错误信息",
  "errors": {
    "字段名": ["错误说明"]
  }
}
```

- 表单验证失败默认返回 422 + ValidationException（已符合格式）；
- 授权失败统一用 `abort(403, '权限不足')`；
- 其他异常推荐抛出自定义 Exception 并实现标准 JSON 响应结构。
  ```php
  // 错误处理中间件使用示例
  class ApiErrorHandler
  {
      public function handle(Request $request, Closure $next)
      {
          try {
              return $next($request);
          } catch (ValidationException $e) {
              return response()->json([
                  'message' => '提供的数据无效',
                  'errors' => $e->errors(),
              ], 422);
          } catch (Throwable $e) {
              // 记录日志但不暴露敏感信息
              Log::error('API错误', [
                  'message' => $e->getMessage(),
                  'trace' => $e->getTraceAsString()
              ]);
              
              return response()->json([
                  'message' => '处理请求时发生错误，请稍后重试'
              ], 500);
          }
      }
  }
  ```

## 🧰 工具与最佳实践 [best-practices]
- 所有模块 Seeder 建议放入 database/seeders/modules/ 下。
- 日志记录时避免记录敏感信息如密码、token等。
  ```php
  // 正确的日志记录
  Log::info('用户登录', [
      'user_id' => $user->id,
      'ip' => $request->ip()
  ]);
  
  // 错误的日志记录 - 不要这样做！
  Log::info('用户登录', [
      'user_id' => $user->id,
      'password' => $request->password,  // 不应记录密码
      'token' => $token                  // 不应记录令牌
  ]);
  ```

> 本规范用于指导 Laravel 后端开发、权限判断与错误处理，适配 Cursor AI 规则生成与自动补全。