<?php

return [

    'models' => [

        /*
         * When using the "HasPermissions" trait from this package, we need to know which
         * Eloquent model should be used to retrieve your permissions. Of course, it
         * is often just the "Permission" model but you may use whatever you like.
         *
         * The model you want to use as a Permission model needs to implement the
         * `Spatie\Permission\Contracts\Permission` contract.
         *
         * 当使用此包中的"HasPermissions"特性时，我们需要知道应该使用哪个
         * Eloquent模型来检索您的权限。当然，它通常只是"Permission"模型，
         * 但您可以使用任何您喜欢的模型。
         *
         * 您希望用作Permission模型的模型需要实现
         * `Spatie\Permission\Contracts\Permission`契约。
         */

        'permission' => Spatie\Permission\Models\Permission::class,

        /*
         * When using the "HasRoles" trait from this package, we need to know which
         * Eloquent model should be used to retrieve your roles. Of course, it
         * is often just the "Role" model but you may use whatever you like.
         *
         * The model you want to use as a Role model needs to implement the
         * `Spatie\Permission\Contracts\Role` contract.
         *
         * 当使用此包中的"HasRoles"特性时，我们需要知道应该使用哪个
         * Eloquent模型来检索您的角色。当然，它通常只是"Role"模型，
         * 但您可以使用任何您喜欢的模型。
         *
         * 您希望用作Role模型的模型需要实现
         * `Spatie\Permission\Contracts\Role`契约。
         */

        'role' => Spatie\Permission\Models\Role::class,

    ],

    'table_names' => [

        /*
         * When using the "HasRoles" trait from this package, we need to know which
         * table should be used to retrieve your roles. We have chosen a basic
         * default value but you may easily change it to any table you like.
         *
         * 当使用此包中的"HasRoles"特性时，我们需要知道应该使用哪个
         * 表来检索您的角色。我们选择了一个基本的默认值，
         * 但您可以轻松地将其更改为任何您喜欢的表。
         */

        'roles' => 'roles',

        /*
         * When using the "HasPermissions" trait from this package, we need to know which
         * table should be used to retrieve your permissions. We have chosen a basic
         * default value but you may easily change it to any table you like.
         *
         * 当使用此包中的"HasPermissions"特性时，我们需要知道应该使用哪个
         * 表来检索您的权限。我们选择了一个基本的默认值，
         * 但您可以轻松地将其更改为任何您喜欢的表。
         */

        'permissions' => 'permissions',

        /*
         * When using the "HasPermissions" trait from this package, we need to know which
         * table should be used to retrieve your models permissions. We have chosen a
         * basic default value but you may easily change it to any table you like.
         *
         * 当使用此包中的"HasPermissions"特性时，我们需要知道应该使用哪个
         * 表来检索您的模型权限。我们选择了一个基本的默认值，
         * 但您可以轻松地将其更改为任何您喜欢的表。
         */

        'model_has_permissions' => 'model_has_permissions',

        /*
         * When using the "HasRoles" trait from this package, we need to know which
         * table should be used to retrieve your models roles. We have chosen a
         * basic default value but you may easily change it to any table you like.
         *
         * 当使用此包中的"HasRoles"特性时，我们需要知道应该使用哪个
         * 表来检索您的模型角色。我们选择了一个基本的默认值，
         * 但您可以轻松地将其更改为任何您喜欢的表。
         */

        'model_has_roles' => 'model_has_roles',

        /*
         * When using the "HasRoles" trait from this package, we need to know which
         * table should be used to retrieve your roles permissions. We have chosen a
         * basic default value but you may easily change it to any table you like.
         *
         * 当使用此包中的"HasRoles"特性时，我们需要知道应该使用哪个
         * 表来检索您角色的权限。我们选择了一个基本的默认值，
         * 但您可以轻松地将其更改为任何您喜欢的表。
         */

        'role_has_permissions' => 'role_has_permissions',
    ],

    'column_names' => [
        /*
         * Change this if you want to name the related pivots other than defaults
         *
         * 如果您想命名相关的枢纽表字段名称而非默认值，请更改此项
         */
        'role_pivot_key' => null, // default 'role_id',
        'permission_pivot_key' => null, // default 'permission_id',

        /*
         * Change this if you want to name the related model primary key other than
         * `model_id`.
         *
         * For example, this would be nice if your primary keys are all UUIDs. In
         * that case, name this `model_uuid`.
         *
         * 如果您想将相关模型的主键命名为不同于
         * `model_id`的名称，请更改此项。
         *
         * 例如，如果您的主键都是UUID，这将很有用。
         * 在这种情况下，将其命名为`model_uuid`。
         */

        'model_morph_key' => 'model_id',

        /*
         * Change this if you want to use the teams feature and your related model's
         * foreign key is other than `team_id`.
         *
         * 如果您想使用团队功能，并且您相关模型的
         * 外键不是`team_id`，请更改此项。
         */

        'team_foreign_key' => 'team_id',
    ],

    /**
     * 用于测试的标志
     * 在迁移文件中使用，确保团队功能迁移正确
     */
    'testing' => false,

    /*
     * When set to true, the method for checking permissions will be registered on the gate.
     * Set this to false if you want to implement custom logic for checking permissions.
     *
     * 当设置为true时，检查权限的方法将注册到gate。
     * 如果您想实现自定义的权限检查逻辑，请将其设置为false。
     */

    'register_permission_check_method' => true,

    /*
     * When set to true, Laravel\Octane\Events\OperationTerminated event listener will be registered
     * this will refresh permissions on every TickTerminated, TaskTerminated and RequestTerminated
     * NOTE: This should not be needed in most cases, but an Octane/Vapor combination benefited from it.
     *
     * 当设置为true时，将注册Laravel\Octane\Events\OperationTerminated事件监听器
     * 这将在每个TickTerminated、TaskTerminated和RequestTerminated上刷新权限
     * 注意：在大多数情况下不需要此功能，但Octane/Vapor组合从中受益。
     */
    'register_octane_reset_listener' => false,

    /*
     * Events will fire when a role or permission is assigned/unassigned:
     * \Spatie\Permission\Events\RoleAttached
     * \Spatie\Permission\Events\RoleDetached
     * \Spatie\Permission\Events\PermissionAttached
     * \Spatie\Permission\Events\PermissionDetached
     *
     * To enable, set to true, and then create listeners to watch these events.
     *
     * 当角色或权限被分配/取消分配时将触发事件：
     * \Spatie\Permission\Events\RoleAttached
     * \Spatie\Permission\Events\RoleDetached
     * \Spatie\Permission\Events\PermissionAttached
     * \Spatie\Permission\Events\PermissionDetached
     *
     * 要启用，请设置为true，然后创建监听器来监视这些事件。
     */
    'events_enabled' => false,

    /*
     * Teams Feature.
     * When set to true the package implements teams using the 'team_foreign_key'.
     * If you want the migrations to register the 'team_foreign_key', you must
     * set this to true before doing the migration.
     * If you already did the migration then you must make a new migration to also
     * add 'team_foreign_key' to 'roles', 'model_has_roles', and 'model_has_permissions'
     * (view the latest version of this package's migration file)
     *
     * 团队功能。
     * 当设置为true时，包使用'team_foreign_key'实现团队功能。
     * 如果您希望迁移注册'team_foreign_key'，您必须
     * 在执行迁移之前将其设置为true。
     * 如果您已经执行了迁移，那么您必须创建一个新的迁移来
     * 将'team_foreign_key'添加到'roles'，'model_has_roles'和'model_has_permissions'
     * （查看此包最新版本的迁移文件）
     */

    'teams' => true,

    /*
     * The class to use to resolve the permissions team id
     *
     * 用于解析权限团队ID的类
     */
    'team_resolver' => \Spatie\Permission\DefaultTeamResolver::class,

    /*
     * Passport Client Credentials Grant
     * When set to true the package will use Passports Client to check permissions
     *
     * Passport客户端凭证授权
     * 当设置为true时，包将使用Passport客户端来检查权限
     */

    'use_passport_client_credentials' => false,

    /*
     * When set to true, the required permission names are added to exception messages.
     * This could be considered an information leak in some contexts, so the default
     * setting is false here for optimum safety.
     *
     * 当设置为true时，所需的权限名称会添加到异常消息中。
     * 在某些情况下，这可能被视为信息泄漏，因此为了最佳安全性，
     * 默认设置为false。
     */

    'display_permission_in_exception' => false,

    /*
     * When set to true, the required role names are added to exception messages.
     * This could be considered an information leak in some contexts, so the default
     * setting is false here for optimum safety.
     *
     * 当设置为true时，所需的角色名称会添加到异常消息中。
     * 在某些情况下，这可能被视为信息泄漏，因此为了最佳安全性，
     * 默认设置为false。
     */

    'display_role_in_exception' => false,

    /*
     * By default wildcard permission lookups are disabled.
     * See documentation to understand supported syntax.
     *
     * 默认情况下，通配符权限查找功能被禁用。
     * 请参阅文档以了解支持的语法。
     */

    'enable_wildcard_permission' => false,

    /*
     * The class to use for interpreting wildcard permissions.
     * If you need to modify delimiters, override the class and specify its name here.
     *
     * 用于解释通配符权限的类。
     * 如果您需要修改分隔符，请覆盖该类并在此处指定其名称。
     */
    // 'wildcard_permission' => Spatie\Permission\WildcardPermission::class,

    /* Cache-specific settings 
     *
     * 缓存特定设置
     */

    'cache' => [

        /*
         * By default all permissions are cached for 24 hours to speed up performance.
         * When permissions or roles are updated the cache is flushed automatically.
         *
         * 默认情况下，所有权限都会被缓存24小时以提高性能。
         * 当权限或角色更新时，缓存会自动刷新。
         */

        'expiration_time' => \DateInterval::createFromDateString('24 hours'),

        /*
         * The cache key used to store all permissions.
         *
         * 用于存储所有权限的缓存键。
         */

        'key' => 'spatie.permission.cache',

        /*
         * You may optionally indicate a specific cache driver to use for permission and
         * role caching using any of the `store` drivers listed in the cache.php config
         * file. Using 'default' here means to use the `default` set in cache.php.
         *
         * 您可以选择指定一个特定的缓存驱动来用于权限和
         * 角色缓存，使用cache.php配置文件中列出的任何`store`驱动。
         * 在这里使用'default'意味着使用cache.php中设置的`default`。
         */

        'store' => 'default',
    ],
];
