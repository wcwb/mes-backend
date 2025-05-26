<?php

/**
 * 权限与团队管理系统测试运行脚本
 * 
 * 使用方法：
 * php tests/run_permission_tests.php
 * 
 * 或者使用 PHPUnit 直接运行：
 * ./vendor/bin/phpunit tests/Unit/UserModelTest.php
 * ./vendor/bin/phpunit tests/Unit/TeamModelTest.php
 * ./vendor/bin/phpunit tests/Unit/RolePermissionTest.php
 * ./vendor/bin/phpunit tests/Feature/PermissionSystemTest.php
 * ./vendor/bin/phpunit tests/Feature/TeamManagementTest.php
 */

echo "=== 权限与团队管理系统测试运行脚本 ===\n\n";

// 检查是否在正确的目录
if (!file_exists('artisan')) {
    echo "错误：请在Laravel项目根目录下运行此脚本\n";
    exit(1);
}

// 检查PHPUnit是否存在
if (!file_exists('vendor/bin/phpunit')) {
    echo "错误：PHPUnit未安装，请运行 composer install\n";
    exit(1);
}

$testFiles = [
    'tests/Unit/UserModelTest.php' => '用户模型单元测试',
    'tests/Unit/TeamModelTest.php' => '团队模型单元测试',
    'tests/Unit/RolePermissionTest.php' => '角色权限单元测试',
    'tests/Feature/PermissionSystemTest.php' => '权限系统功能测试',
    'tests/Feature/TeamManagementTest.php' => '团队管理功能测试',
    'tests/Feature/AdvancedPermissionTest.php' => '高级权限功能测试',
    'tests/Feature/ExtendedPermissionTest.php' => '扩展权限功能测试',
    'tests/Feature/TroubleshootingTest.php' => '故障排除测试',
    'tests/Feature/DocumentationExamplesTest.php' => '文档示例测试'
];

echo "可用的测试文件：\n";
foreach ($testFiles as $file => $description) {
    $exists = file_exists($file) ? '✓' : '✗';
    echo "  {$exists} {$description} ({$file})\n";
}

echo "\n选择运行模式：\n";
echo "1. 运行所有权限相关测试\n";
echo "2. 运行单元测试\n";
echo "3. 运行功能测试\n";
echo "4. 选择特定测试文件\n";
echo "5. 显示测试统计信息\n";
echo "0. 退出\n\n";

$choice = readline("请输入选择 (0-5): ");

switch ($choice) {
    case '1':
        echo "\n=== 运行所有权限相关测试 ===\n";
        runCommand('./vendor/bin/phpunit tests/Unit/UserModelTest.php tests/Unit/TeamModelTest.php tests/Unit/RolePermissionTest.php tests/Feature/PermissionSystemTest.php tests/Feature/TeamManagementTest.php tests/Feature/AdvancedPermissionTest.php tests/Feature/ExtendedPermissionTest.php tests/Feature/TroubleshootingTest.php tests/Feature/DocumentationExamplesTest.php');
        break;

    case '2':
        echo "\n=== 运行单元测试 ===\n";
        runCommand('./vendor/bin/phpunit tests/Unit/UserModelTest.php tests/Unit/TeamModelTest.php tests/Unit/RolePermissionTest.php');
        break;

    case '3':
        echo "\n=== 运行功能测试 ===\n";
        runCommand('./vendor/bin/phpunit tests/Feature/PermissionSystemTest.php tests/Feature/TeamManagementTest.php tests/Feature/AdvancedPermissionTest.php tests/Feature/ExtendedPermissionTest.php tests/Feature/TroubleshootingTest.php tests/Feature/DocumentationExamplesTest.php');
        break;

    case '4':
        echo "\n选择要运行的测试文件：\n";
        $i = 1;
        $fileList = [];
        foreach ($testFiles as $file => $description) {
            if (file_exists($file)) {
                echo "{$i}. {$description}\n";
                $fileList[$i] = $file;
                $i++;
            }
        }

        $fileChoice = readline("\n请输入文件编号: ");
        if (isset($fileList[$fileChoice])) {
            $selectedFile = $fileList[$fileChoice];
            echo "\n=== 运行 {$testFiles[$selectedFile]} ===\n";
            runCommand("./vendor/bin/phpunit {$selectedFile}");
        } else {
            echo "无效的选择\n";
        }
        break;

    case '5':
        echo "\n=== 测试统计信息 ===\n";
        showTestStats();
        break;

    case '0':
        echo "退出\n";
        break;

    default:
        echo "无效的选择\n";
        break;
}

function runCommand($command)
{
    echo "执行命令: {$command}\n\n";

    $startTime = microtime(true);
    $output = [];
    $returnCode = 0;

    exec($command . ' 2>&1', $output, $returnCode);

    $endTime = microtime(true);
    $duration = round($endTime - $startTime, 2);

    foreach ($output as $line) {
        echo $line . "\n";
    }

    echo "\n";
    echo "=== 执行完成 ===\n";
    echo "执行时间: {$duration} 秒\n";
    echo "返回码: {$returnCode}\n";

    if ($returnCode === 0) {
        echo "状态: ✓ 成功\n";
    } else {
        echo "状态: ✗ 失败\n";
    }

    echo "\n";
}

function showTestStats()
{
    $testFiles = [
        'tests/Unit/UserModelTest.php',
        'tests/Unit/TeamModelTest.php',
        'tests/Unit/RolePermissionTest.php',
        'tests/Feature/PermissionSystemTest.php',
        'tests/Feature/TeamManagementTest.php'
    ];

    $totalTests = 0;
    $totalLines = 0;

    foreach ($testFiles as $file) {
        if (file_exists($file)) {
            $content = file_get_contents($file);
            $lines = substr_count($content, "\n") + 1;
            $tests = preg_match_all('/\/\*\*\s*@test\s*\*\//', $content);

            echo "文件: {$file}\n";
            echo "  行数: {$lines}\n";
            echo "  测试方法数: {$tests}\n\n";

            $totalTests += $tests;
            $totalLines += $lines;
        }
    }

    echo "=== 总计 ===\n";
    echo "总测试文件数: " . count($testFiles) . "\n";
    echo "总测试方法数: {$totalTests}\n";
    echo "总代码行数: {$totalLines}\n";

    // 显示测试覆盖的功能
    echo "\n=== 测试覆盖功能 ===\n";
    echo "✓ 用户模型基础功能\n";
    echo "✓ 用户安全权限检查方法\n";
    echo "✓ 用户跨团队角色管理\n";
    echo "✓ 用户团队上下文切换\n";
    echo "✓ 团队创建和管理\n";
    echo "✓ 团队成员关系管理\n";
    echo "✓ 角色和权限创建\n";
    echo "✓ 多团队权限隔离\n";
    echo "✓ Spatie Permission包集成\n";
    echo "✓ 权限系统完整流程\n";
    echo "✓ 团队管理完整流程\n";
    echo "✓ 复杂多团队场景\n";
}

echo "\n提示：\n";
echo "- 运行测试前请确保数据库配置正确\n";
echo "- 测试会使用 RefreshDatabase trait 重置数据库\n";
echo "- 如需调试特定测试，可以添加 --filter 参数\n";
echo "- 例如：./vendor/bin/phpunit tests/Unit/UserModelTest.php --filter user_can_assign_role_safely\n";
echo "\n";
