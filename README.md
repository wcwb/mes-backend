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

这是一个基于 Laravel 12 构建的多团队权限管理系统（MES-Backend），集成了以下核心组件：

- **Laravel Jetstream**: 提供团队管理、个人资料管理等功能
- **Laravel Fortify**: 实现身份验证
- **Laravel Sanctum**: API 令牌身份验证
- **Spatie Permission**: 灵活的角色与权限系统

本项目实现了在多团队环境下的完整角色权限隔离解决方案，支持团队级别的权限控制，适用于企业级多租户应用场景。

## 核心功能

### 🏢 多团队管理
- **团队创建与管理**: 用户可以创建和管理多个团队
- **团队切换**: 支持在不同团队间无缝切换
- **团队角色**: 内置四种团队角色（viewer、creator、editor、owner）
- **团队删除**: 智能处理团队删除时的用户关系和权限清理
  - 自动将"仅属于被删除团队"的用户转移到默认团队
  - 清理团队相关的权限和角色关系
  - 支持软删除和强制删除两种模式

### 🔐 权限管理系统
- **角色与权限**: 完整的 RBAC（基于角色的访问控制）系统
- **权限隔离**: 基于团队的权限隔离实现
- **动态权限**: 支持运行时权限检查和分配
- **权限继承**: 支持角色权限继承和用户直接权限
- **模块化权限**: 按功能模块组织权限结构

### 🚀 API 接口
- **RESTful API**: 完整的 REST API 接口
- **API 授权**: 支持 API 路由的权限验证
- **团队上下文**: API 自动识别当前团队上下文
- **统一响应格式**: 标准化的 API 响应结构

### 🛡️ 安全特性
- **CSRF 保护**: 完整的 CSRF 攻击防护
- **CORS 配置**: 灵活的跨域资源共享配置
- **令牌认证**: 基于 Sanctum 的 API 令牌认证
- **中间件保护**: 多层中间件安全验证

## 技术架构

### 中间件系统
- **SetSpatieTeamId**: 自动设置当前团队上下文，实现权限隔离
- **CheckPermission**: 验证用户是否拥有指定权限
- **CheckRole**: 验证用户是否拥有指定角色

### 控制器架构
- **AuthController**: 用户认证、登录、注册、登出
- **TeamController**: 团队管理、创建、更新、删除
- **SwitchTeamController**: 团队切换功能
- **RoleController**: 角色的增删改查、分配/移除权限
- **PermissionController**: 权限的增删改查
- **UserRoleController**: 用户角色的分配/移除
- **UserPermissionController**: 用户直接权限的分配/移除、权限检查

### 模型设计
- **User**: 用户模型，支持多团队关联
- **Team**: 团队模型，集成软删除功能
- **Membership**: 团队成员关系模型
- **Role & Permission**: 基于 Spatie Permission 的角色权限模型

### 观察者模式
- **TeamObserver**: 监听团队生命周期事件，处理团队删除时的用户关系和权限清理

## API 接口文档

### 认证接口
```
POST /api/register          # 用户注册
POST /api/login             # 用户登录
POST /api/logout            # 用户登出
GET  /api/user              # 获取当前用户信息
```

### 团队管理接口
```
GET    /api/teams           # 获取用户团队列表
POST   /api/teams           # 创建新团队
GET    /api/teams/{id}      # 获取团队详情
PUT    /api/teams/{id}      # 更新团队信息
DELETE /api/teams/{id}      # 删除团队
POST   /api/switch-team     # 切换当前团队
```

### 权限管理接口
```
GET    /api/roles           # 获取角色列表
POST   /api/roles           # 创建角色
PUT    /api/roles/{id}      # 更新角色
DELETE /api/roles/{id}      # 删除角色
GET    /api/permissions     # 获取权限列表
POST   /api/permissions     # 创建权限
```

## 安装与配置

### 系统要求

- PHP >= 8.2
- Composer
- MySQL 或其他 Laravel 支持的数据库
- Node.js (可选，用于前端资源编译)

### 安装步骤

1. **克隆仓库**
```bash
git clone https://github.com/wcwb/mes-backend.git
cd mes-backend
```

2. **安装依赖**
```bash
composer install
```

3. **环境配置**
```bash
cp .env.example .env
php artisan key:generate
```

4. **配置数据库连接**（编辑 .env 文件）
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mes
DB_USERNAME=root
DB_PASSWORD=
```

5. **运行迁移和种子数据**
```bash
php artisan migrate
php artisan db:seed
```

6. **生成 IDE 辅助文件**（可选）
```bash
php artisan ide-helper:generate
php artisan ide-helper:models
php artisan ide-helper:meta
```

7. **启动开发服务器**
```bash
php artisan serve
```

## CORS 配置

如果前端出现 CSRF Cookie 失败的问题，请检查以下配置：

1. **后端配置** (`config/cors.php`)：
```php
'supports_credentials' => true,
'paths' => ['api/*', '/sanctum/csrf-cookie', 'login', 'logout'],
'allowed_origins' => ['http://localhost:3000'], // 前端域名
```

2. **前端 Axios 配置**：
```javascript
axios.defaults.withCredentials = true;
axios.defaults.baseURL = 'http://localhost:8000';
```

3. **前端登录流程**：
```javascript
// 先获取 CSRF Cookie
await axios.get('/sanctum/csrf-cookie');
// 然后发送登录请求
await axios.post('/api/login', credentials);
```

## 测试

### 运行所有测试
```bash
php artisan test
```

### 运行特定测试套件
```bash
# 权限系统测试
php artisan test --filter=PermissionSystemTest

# 团队管理测试
php artisan test --filter=TeamManagementTest

# 团队删除功能测试
php artisan test --filter=DeleteTeamBehaviorTest

# 高级权限测试
php artisan test --filter=AdvancedPermissionTest
```

### 测试覆盖率
```bash
php artisan test --coverage
```

## 开发工具

### 代码质量
- **Laravel Pint**: 代码格式化工具
- **PHPStan**: 静态代码分析
- **Pest**: 现代化的 PHP 测试框架

### IDE 支持
- **Laravel IDE Helper**: 提供完整的 IDE 代码提示
- **PHPStorm Meta**: PHPStorm 专用的元数据文件

## 文档

详细的功能文档存放在 `docs` 目录：

- [HasTeams 使用指南](docs/has-teams-usage-guide.md)
- [后端数据结构](docs/后端数据结构/)
- [权限与团队管理](docs/权限与团队管理/)
- [需求文档](docs/需求/)

## 部署

### 生产环境部署

1. **环境变量配置**
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
```

2. **优化配置**
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

3. **数据库迁移**
```bash
php artisan migrate --force
```

### Docker 部署（可选）
```bash
# 构建镜像
docker build -t mes-backend .

# 运行容器
docker run -d -p 8000:8000 mes-backend
```

## 贡献指南

欢迎提交 Pull Request 或创建 Issue 来改进本项目。

### 开发流程
1. Fork 本仓库
2. 创建功能分支 (`git checkout -b feature/amazing-feature`)
3. 提交更改 (`git commit -m 'Add some amazing feature'`)
4. 推送到分支 (`git push origin feature/amazing-feature`)
5. 创建 Pull Request

### 代码规范
- 遵循 PSR-12 编码标准
- 使用 Laravel Pint 进行代码格式化
- 编写测试用例覆盖新功能
- 添加适当的中文注释

## 许可证

本项目基于 [MIT 许可证](LICENSE) 开源。

## 更新日志

### v1.2.0 (最新)
- ✨ 新增团队切换 API 接口
- 🔧 优化权限检查中间件
- 📝 完善 API 文档和测试用例
- 🐛 修复团队删除时的权限清理问题

### v1.1.0
- ✨ 实现完整的多团队权限隔离
- 🔧 添加团队删除功能
- 📝 完善文档和使用指南

### v1.0.0
- 🎉 初始版本发布
- ✨ 基础的用户认证和团队管理功能
