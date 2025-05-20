<?php

namespace App\Http\Controllers\Api;

use App\Helpers\PermissionHelper;
use App\Helpers\TeamConstants;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Spatie\Permission\PermissionRegistrar;

/**
 * 订单API控制器示例
 * 演示如何在API中使用团队权限
 */
class OrderController extends Controller
{
    /**
     * 构造函数
     * 添加适当的中间件
     */
    public function __construct()
    {
        // 确保用户被认证
        $this->middleware('auth:sanctum');
        
        // 确保用户属于至少一个团队
        $this->middleware('ensure.team');
        
        // 确保当前请求使用正确的权限团队上下文
        $this->middleware(function ($request, $next) {
            // 如果用户有当前团队，使用该团队ID
            if ($request->user()->currentTeam) {
                PermissionHelper::setCurrentTeamId($request->user()->currentTeam->id);
            } else {
                // 否则使用默认团队ID
                PermissionHelper::setCurrentTeamId(TeamConstants::DEFAULT_TEAM_ID);
            }
            
            // 刷新用户的权限缓存，确保使用最新的权限数据
            PermissionHelper::refreshUserPermissionCache($request->user());
            
            return $next($request);
        });
    }

    /**
     * 获取订单列表
     * 
     * @param Request $request
     * @return AnonymousResourceCollection|Response
     */
    public function index(Request $request)
    {
        // 检查用户是否有查看订单的权限
        if (!$request->user()->can('view_orders')) {
            return response()->json([
                'message' => '您没有查看订单的权限'
            ], 403);
        }
        
        // 获取与用户团队相关的订单
        // 假设订单模型有team_id字段表示订单所属的团队
        $orders = Order::where('team_id', $request->user()->currentTeam->id)
            ->paginate(15);
        
        return OrderResource::collection($orders);
    }
    
    /**
     * 获取单个订单详情
     * 
     * @param Request $request
     * @param int $id
     * @return OrderResource|Response
     */
    public function show(Request $request, $id)
    {
        // 获取订单
        $order = Order::findOrFail($id);
        
        // 确保订单属于用户当前团队
        if ($order->team_id !== $request->user()->currentTeam->id) {
            return response()->json([
                'message' => '无法访问其他团队的订单'
            ], 403);
        }
        
        // 检查权限
        if (!$request->user()->can('view_orders')) {
            return response()->json([
                'message' => '您没有查看订单的权限'
            ], 403);
        }
        
        return new OrderResource($order);
    }
    
    /**
     * 创建新订单
     * 
     * @param Request $request
     * @return OrderResource|Response
     */
    public function store(Request $request)
    {
        // 检查权限
        if (!$request->user()->can('create_orders')) {
            return response()->json([
                'message' => '您没有创建订单的权限'
            ], 403);
        }
        
        // 验证请求数据
        $validatedData = $request->validate([
            'customer_id' => 'required|integer|exists:customers,id',
            'products' => 'required|array',
            'products.*.id' => 'required|integer|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:1000',
        ]);
        
        // 创建订单（示例逻辑）
        $order = new Order();
        $order->customer_id = $validatedData['customer_id'];
        $order->notes = $validatedData['notes'] ?? '';
        $order->status = 'pending';
        $order->team_id = $request->user()->currentTeam->id; // 重要：记录团队ID
        $order->created_by = $request->user()->id; // 记录创建者
        $order->save();
        
        // 添加订单项（示例逻辑）
        foreach ($validatedData['products'] as $product) {
            $order->items()->create([
                'product_id' => $product['id'],
                'quantity' => $product['quantity'],
                // 其他字段...
            ]);
        }
        
        // 记录审计日志
        \Log::channel('team_management')->info('创建了新订单', [
            'user_id' => $request->user()->id,
            'team_id' => $request->user()->currentTeam->id,
            'order_id' => $order->id
        ]);
        
        return new OrderResource($order);
    }
    
    /**
     * 更新订单
     * 
     * @param Request $request
     * @param int $id
     * @return OrderResource|Response
     */
    public function update(Request $request, $id)
    {
        // 检查权限
        if (!$request->user()->can('update_orders')) {
            return response()->json([
                'message' => '您没有更新订单的权限'
            ], 403);
        }
        
        // 获取订单
        $order = Order::findOrFail($id);
        
        // 确保订单属于用户当前团队
        if ($order->team_id !== $request->user()->currentTeam->id) {
            return response()->json([
                'message' => '无法修改其他团队的订单'
            ], 403);
        }
        
        // 验证请求数据
        $validatedData = $request->validate([
            'status' => 'sometimes|string|in:pending,processing,completed,cancelled',
            'notes' => 'nullable|string|max:1000',
        ]);
        
        // 更新订单
        $order->update($validatedData);
        
        // 记录审计日志
        \Log::channel('team_management')->info('更新了订单', [
            'user_id' => $request->user()->id,
            'team_id' => $request->user()->currentTeam->id,
            'order_id' => $order->id
        ]);
        
        return new OrderResource($order);
    }
    
    /**
     * 删除订单
     * 
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function destroy(Request $request, $id)
    {
        // 检查权限
        if (!$request->user()->can('delete_orders')) {
            return response()->json([
                'message' => '您没有删除订单的权限'
            ], 403);
        }
        
        // 获取订单
        $order = Order::findOrFail($id);
        
        // 确保订单属于用户当前团队
        if ($order->team_id !== $request->user()->currentTeam->id) {
            return response()->json([
                'message' => '无法删除其他团队的订单'
            ], 403);
        }
        
        // 记录审计日志（在删除前记录）
        \Log::channel('security')->warning('删除了订单', [
            'user_id' => $request->user()->id,
            'team_id' => $request->user()->currentTeam->id,
            'order_id' => $order->id
        ]);
        
        // 软删除订单
        $order->delete();
        
        return response()->json([
            'message' => '订单已成功删除'
        ]);
    }
    
    /**
     * 导出订单
     * 
     * @param Request $request
     * @return Response
     */
    public function export(Request $request)
    {
        // 检查权限
        if (!$request->user()->can('export_orders')) {
            return response()->json([
                'message' => '您没有导出订单的权限'
            ], 403);
        }
        
        // 导出逻辑（示例）
        $teamId = $request->user()->currentTeam->id;
        $orders = Order::where('team_id', $teamId)->get();
        
        // ... 生成导出文件的代码 ...
        
        // 记录审计日志
        \Log::channel('team_management')->info('导出了订单数据', [
            'user_id' => $request->user()->id,
            'team_id' => $teamId,
            'count' => $orders->count()
        ]);
        
        return response()->json([
            'message' => '订单导出成功',
            'download_url' => 'https://example.com/exports/orders_' . uniqid() . '.xlsx'
        ]);
    }
} 