# MES-Backend

<p align="center">
  <a href="https://laravel.com" target="_blank">
    <img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo">
  </a>
</p>

<p align="center">
  <a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
  <a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
  <a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
  <a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## 项目介绍

这是一个基于 Laravel 12 构建的多团队权限管理系统，集成了以下核心组件：

- **Laravel Jetstream**: 提供团队管理、个人资料管理等功能
- **Laravel Fortify**: 实现身份验证
- **Laravel Sanctum**: API 令牌身份验证
- **Spatie Permission**: 灵活的角色与权限系统

本项目实现了在多团队环境下的完整角色权限隔离解决方案，支持团队级别的权限控制。

## 核心功能

- **多团队支持**: 用户可以创建和加入多个团队，在团队间自由切换
- **团队角色**: 内置四种团队角色（viewer、creator、editor、owner）
- **权限管理**: 完整的角色与权限 CRUD 接口
- **权限隔离**: 基于团队的权限隔离实现
- **API 授权**: 支持 API 路由的权限验证
- **中间件**: 提供 `permission` 和 `role` 中间件用于访问控制

## 技术架构

### 中间件

- **SetSpatieTeamId**: 自动设置当前团队上下文，实现权限隔离
- **CheckPermission**: 验证用户是否拥有指定权限
- **CheckRole**: 验证用户是否拥有指定角色

### 控制器

- **RoleController**: 角色的增删改查、分配/移除权限
- **PermissionController**: 权限的增删改查
- **UserRoleController**: 用户角色的分配/移除
- **UserPermissionController**: 用户直接权限的分配/移除、权限检查

## 安装与配置

### 系统要求

- PHP >= 8.2
- Composer
- MySQL 或其他 Laravel 支持的数据库

### 安装步骤

1. 克隆仓库
```bash
git clone https://github.com/wcwb/mes-backend.git
cd mes-backend
```

2. 安装依赖
```bash
composer install
```

3. 环境配置
```bash
cp .env.example .env
php artisan key:generate
```

4. 配置数据库连接（编辑 .env 文件）
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mes
DB_USERNAME=root
DB_PASSWORD=
```

5. 运行迁移
```bash
php artisan migrate
```

6. 运行种子数据（可选）
```bash
php artisan db:seed
```

7. 启动开发服务器
```bash
php artisan serve
```

## API 文档

API 文档可通过以下方式访问：

```
http://localhost:8000/api/documentation
```

## 测试

运行测试套件：

```bash
php artisan test
```

## 许可证

本项目基于 [MIT 许可证](LICENSE) 开源。

## 贡献指南

欢迎提交 Pull Request 或创建 Issue 来改进本项目。
