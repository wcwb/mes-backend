# 权限与团队管理系统测试说明

## 📋 测试概览

本项目包含完整的PHPUnit测试套件，用于测试权限与团队管理系统的各个方面。测试分为单元测试和功能测试两大类。

## 📁 测试文件结构

```
tests/
├── Unit/                           # 单元测试
│   ├── UserModelTest.php          # 用户模型测试 (18个测试方法)
│   ├── TeamModelTest.php          # 团队模型测试 (13个测试方法)
│   └── RolePermissionTest.php     # 角色权限测试 (20个测试方法)
├── Feature/                        # 功能测试
│   ├── PermissionSystemTest.php   # 权限系统功能测试 (15个测试方法)
│   └── TeamManagementTest.php     # 团队管理功能测试 (16个测试方法)
├── run_permission_tests.php       # 测试运行脚本
└── README_测试说明.md             # 本文档
```

**总计：82个测试方法，覆盖权限与团队管理的所有核心功能**

## 🚀 快速开始

### 方法1：使用测试运行脚本（推荐）

```bash
# 运行交互式测试脚本
php tests/run_permission_tests.php
```

### 方法2：直接使用PHPUnit

```bash
# 运行所有权限相关测试
./vendor/bin/phpunit tests/Unit/UserModelTest.php tests/Unit/TeamModelTest.php tests/Unit/RolePermissionTest.php tests/Feature/PermissionSystemTest.php tests/Feature/TeamManagementTest.php

# 运行单元测试
./vendor/bin/phpunit tests/Unit/

# 运行功能测试
./vendor/bin/phpunit tests/Feature/

# 运行特定测试文件
./vendor/bin/phpunit tests/Unit/UserModelTest.php

# 运行特定测试方法
./vendor/bin/phpunit tests/Unit/UserModelTest.php --filter user_can_assign_role_safely
```

## 📊 测试详细说明

### 单元测试 (Unit Tests)

#### 1. UserModelTest.php - 用户模型测试
测试用户模型的核心功能和安全权限方法：

- **基础功能测试**
  - 用户创建和团队关联
  - 团队上下文设置
  
- **安全权限方法测试**
  - `assignRoleSafely()` - 安全角色分配
  - `hasPermissionToSafely()` - 安全权限检查
  - `canSafely()` - 权限检查别名
  - `hasAnyPermissionSafely()` - 多权限检查
  - `removeRoleSafely()` - 安全角色移除
  - `syncRolesSafely()` - 安全角色同步

- **跨团队功能测试**
  - `assignRoleInTeam()` - 指定团队角色分配
  - `hasPermissionInTeam()` - 指定团队权限检查
  - `getRolesInTeam()` - 获取团队角色
  - `getAllRoles()` - 获取所有角色
  - `withTeamContext()` - 团队上下文切换

#### 2. TeamModelTest.php - 团队模型测试
测试团队模型的基础功能和关系管理：

- **团队基础功能**
  - 团队创建和属性管理
  - 个人团队标志
  - 团队更新和删除

- **用户关系管理**
  - 用户添加到团队
  - 多用户不同角色管理
  - 用户角色更新
  - 用户从团队移除

- **权限隔离测试**
  - 团队间角色和权限隔离
  - 同名角色在不同团队的独立性

#### 3. RolePermissionTest.php - 角色权限测试
测试Spatie Permission包在多团队环境下的功能：

- **角色和权限创建**
  - 带团队ID的角色创建
  - 带团队ID的权限创建
  - 同名角色/权限在不同团队的隔离

- **角色权限关联**
  - 角色分配权限
  - 跨团队权限分配限制
  - 权限继承测试

- **用户角色分配**
  - 团队上下文中的角色分配
  - 角色移除和同步
  - 直接权限分配

- **多Guard支持**
  - 不同Guard的角色权限隔离

### 功能测试 (Feature Tests)

#### 4. PermissionSystemTest.php - 权限系统功能测试
测试完整的权限系统流程和用户交互：

- **完整权限流程**
  - 用户角色分配和权限检查
  - 安全方法的实际使用
  - 不存在权限的优雅处理

- **多团队场景**
  - 团队切换和权限隔离
  - 跨团队操作
  - 复杂多团队权限管理

- **高级功能**
  - `withTeamContext()` 方法的实际应用
  - 管理员权限管理
  - 自动团队上下文设置

#### 5. TeamManagementTest.php - 团队管理功能测试
测试团队管理的完整功能和业务流程：

- **团队创建和管理**
  - 团队创建和所有者设置
  - 团队信息更新
  - 团队删除和清理

- **成员管理**
  - 成员邀请和添加
  - 成员角色管理
  - 成员移除和离开

- **权限管理**
  - 团队成员权限分配
  - 权限角色更新
  - 多角色权限组合

- **高级场景**
  - 所有权转移
  - 多团队成员管理
  - 个人团队处理

## 🔧 测试配置

### 环境要求

- PHP 8.0+
- Laravel 9.0+
- PHPUnit 9.0+
- MySQL/PostgreSQL 测试数据库

### 数据库配置

测试使用 `RefreshDatabase` trait，会在每个测试前重置数据库。确保测试数据库配置正确：

```php
// .env.testing
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_test_database
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

### 依赖包

确保以下包已安装：

```json
{
    "require-dev": {
        "phpunit/phpunit": "^9.0",
        "laravel/jetstream": "^2.0",
        "spatie/laravel-permission": "^5.0"
    }
}
```

## 📈 测试覆盖范围

### 核心功能覆盖

- ✅ **用户权限管理** (100%)
  - 安全权限检查方法
  - 跨团队权限验证
  - 权限上下文管理

- ✅ **角色管理** (100%)
  - 角色分配和移除
  - 角色同步
  - 多团队角色隔离

- ✅ **团队管理** (100%)
  - 团队创建和删除
  - 成员管理
  - 所有权转移

- ✅ **权限系统集成** (100%)
  - Spatie Permission包集成
  - 多Guard支持
  - 数据库关系完整性

### 边界情况覆盖

- ✅ 不存在的权限处理
- ✅ 跨团队权限隔离
- ✅ 空角色和权限处理
- ✅ 并发团队上下文切换
- ✅ 数据库约束验证

## 🐛 调试测试

### 运行特定测试

```bash
# 运行特定测试方法
./vendor/bin/phpunit tests/Unit/UserModelTest.php --filter user_can_assign_role_safely

# 显示详细输出
./vendor/bin/phpunit tests/Unit/UserModelTest.php --verbose

# 显示测试覆盖率
./vendor/bin/phpunit tests/Unit/UserModelTest.php --coverage-text
```

### 常见问题

1. **数据库连接错误**
   - 检查 `.env.testing` 配置
   - 确保测试数据库存在且可访问

2. **权限相关错误**
   - 确保 Spatie Permission 包正确安装
   - 检查数据库迁移是否完整

3. **Factory 错误**
   - 确保 User 和 Team 的 Factory 存在
   - 检查 Factory 定义是否正确

## 📝 编写新测试

### 测试命名规范

```php
/** @test */
public function user_can_perform_specific_action()
{
    // 测试代码
}
```

### 测试结构

```php
// 1. 准备 (Arrange)
$user = User::factory()->create();
$team = Team::factory()->create();

// 2. 执行 (Act)
$user->assignRoleSafely('editor');

// 3. 断言 (Assert)
$this->assertTrue($user->hasRole('editor'));
```

### 最佳实践

1. **使用描述性的测试方法名**
2. **每个测试只验证一个功能点**
3. **使用适当的断言方法**
4. **清理测试数据（RefreshDatabase已处理）**
5. **添加中文注释说明测试目的**

## 🎯 持续集成

### GitHub Actions 配置示例

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    steps:
    - uses: actions/checkout@v2
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.1'
        
    - name: Install dependencies
      run: composer install
      
    - name: Run tests
      run: ./vendor/bin/phpunit tests/Unit/ tests/Feature/
```

## 📚 相关文档

- [权限与团队管理系统文档](../docs/权限与团队管理/)
- [Laravel Testing 官方文档](https://laravel.com/docs/testing)
- [PHPUnit 官方文档](https://phpunit.de/documentation.html)
- [Spatie Permission 文档](https://spatie.be/docs/laravel-permission)

---

**注意**：运行测试前请确保已完成系统的基础配置，包括数据库迁移、种子数据等。测试会自动处理数据库重置，但不会影响开发环境的数据。 