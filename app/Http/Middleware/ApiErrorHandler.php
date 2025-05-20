<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class ApiErrorHandler
{
    /**
     * 处理传入的请求，捕获可能的异常并返回统一的错误响应
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            $response = $next($request);
            
            // 如果是错误相应，但没有消息，添加默认消息
            if ($this->isErrorResponse($response) && !isset($response->original['message'])) {
                $statusCode = $response->getStatusCode();
                $message = $this->getDefaultMessageForStatusCode($statusCode);
                
                // 修改响应添加消息
                $content = json_decode($response->getContent(), true) ?: [];
                $content['message'] = $message;
                $response->setContent(json_encode($content));
            }
            
            return $response;
        } catch (ValidationException $e) {
            // 处理验证异常
            return $this->handleValidationException($e, $request);
        } catch (AuthenticationException $e) {
            // 处理认证异常
            return $this->handleAuthenticationException($e, $request);
        } catch (AuthorizationException $e) {
            // 处理授权异常
            return $this->handleAuthorizationException($e, $request);
        } catch (ModelNotFoundException $e) {
            // 处理模型未找到异常
            return $this->handleModelNotFoundException($e, $request);
        } catch (NotFoundHttpException $e) {
            // 处理路由未找到异常
            return $this->handleNotFoundHttpException($e, $request);
        } catch (HttpException $e) {
            // 处理HTTP异常
            return $this->handleHttpException($e, $request);
        } catch (Throwable $e) {
            // 处理其他所有异常
            return $this->handleGenericException($e, $request);
        }
    }
    
    /**
     * 检查响应是否为错误响应
     * 
     * @param mixed $response
     * @return bool
     */
    protected function isErrorResponse($response): bool
    {
        return $response && method_exists($response, 'getStatusCode') 
            && $response->getStatusCode() >= 400;
    }
    
    /**
     * 获取HTTP状态码对应的默认消息
     * 
     * @param int $statusCode
     * @return string
     */
    protected function getDefaultMessageForStatusCode(int $statusCode): string
    {
        $messages = [
            400 => __('请求参数有误'),
            401 => __('未授权，请先登录'),
            403 => __('没有权限执行此操作'),
            404 => __('请求的资源不存在'),
            405 => __('请求方法不允许'),
            422 => __('验证失败，请检查输入'),
            429 => __('请求过于频繁，请稍后重试'),
            500 => __('服务器内部错误'),
            503 => __('服务暂时不可用'),
        ];
        
        return $messages[$statusCode] ?? __('未知错误');
    }
    
    /**
     * 处理验证异常
     * 
     * @param ValidationException $e
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    protected function handleValidationException(ValidationException $e, Request $request)
    {
        Log::channel('api_errors')->info('API验证失败', [
            'uri' => $request->getRequestUri(),
            'method' => $request->method(),
            'user_id' => $request->user()->id ?? null,
            'errors' => $e->errors()
        ]);
        
        return response()->json([
            'message' => __('提供的数据无效'),
            'errors' => $e->errors(),
        ], $e->status);
    }
    
    /**
     * 处理认证异常
     * 
     * @param AuthenticationException $e
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    protected function handleAuthenticationException(AuthenticationException $e, Request $request)
    {
        Log::channel('api_errors')->info('API认证失败', [
            'uri' => $request->getRequestUri(),
            'method' => $request->method(),
            'ip' => $request->ip()
        ]);
        
        return response()->json([
            'message' => $e->getMessage() ?: __('未经授权的访问'),
        ], 401);
    }
    
    /**
     * 处理授权异常
     * 
     * @param AuthorizationException $e
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    protected function handleAuthorizationException(AuthorizationException $e, Request $request)
    {
        Log::channel('api_errors')->warning('API授权失败', [
            'uri' => $request->getRequestUri(),
            'method' => $request->method(),
            'user_id' => $request->user()->id ?? null,
            'ip' => $request->ip(),
            'error' => $e->getMessage()
        ]);
        
        return response()->json([
            'message' => $e->getMessage() ?: __('您没有执行此操作的权限'),
        ], 403);
    }
    
    /**
     * 处理模型未找到异常
     * 
     * @param ModelNotFoundException $e
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    protected function handleModelNotFoundException(ModelNotFoundException $e, Request $request)
    {
        $model = class_basename($e->getModel());
        
        Log::channel('api_errors')->info('API请求的模型不存在', [
            'uri' => $request->getRequestUri(),
            'method' => $request->method(),
            'user_id' => $request->user()->id ?? null,
            'model' => $model,
            'id' => $request->route()->parameters()
        ]);
        
        return response()->json([
            'message' => __('请求的:resource不存在', ['resource' => $this->humanize($model)]),
        ], 404);
    }
    
    /**
     * 处理路由未找到异常
     * 
     * @param NotFoundHttpException $e
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    protected function handleNotFoundHttpException(NotFoundHttpException $e, Request $request)
    {
        Log::channel('api_errors')->info('API路由不存在', [
            'uri' => $request->getRequestUri(),
            'method' => $request->method(),
            'ip' => $request->ip()
        ]);
        
        return response()->json([
            'message' => __('请求的资源不存在'),
        ], 404);
    }
    
    /**
     * 处理HTTP异常
     * 
     * @param HttpException $e
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    protected function handleHttpException(HttpException $e, Request $request)
    {
        $statusCode = $e->getStatusCode();
        
        Log::channel('api_errors')->warning('API请求HTTP异常', [
            'uri' => $request->getRequestUri(),
            'method' => $request->method(),
            'user_id' => $request->user()->id ?? null,
            'status_code' => $statusCode,
            'error' => $e->getMessage()
        ]);
        
        return response()->json([
            'message' => $e->getMessage() ?: $this->getDefaultMessageForStatusCode($statusCode),
        ], $statusCode);
    }
    
    /**
     * 处理通用异常
     * 
     * @param Throwable $e
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    protected function handleGenericException(Throwable $e, Request $request)
    {
        Log::channel('api_errors')->error('API请求发生未捕获的异常', [
            'uri' => $request->getRequestUri(),
            'method' => $request->method(),
            'user_id' => $request->user()->id ?? null,
            'ip' => $request->ip(),
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $this->formatStackTrace($e)
        ]);
        
        // 生产环境隐藏具体错误信息
        $message = config('app.debug')
            ? __('服务器错误：:error', ['error' => $e->getMessage()])
            : __('处理请求时发生错误，请稍后重试');
            
        return response()->json([
            'message' => $message,
        ], 500);
    }
    
    /**
     * 将类名转为人类可读的形式
     * 
     * @param string $className
     * @return string
     */
    protected function humanize(string $className): string
    {
        $map = [
            'User' => '用户',
            'Team' => '团队',
            'Role' => '角色',
            'Permission' => '权限',
            'Order' => '订单'
        ];
        
        return $map[$className] ?? $className;
    }
    
    /**
     * 格式化堆栈跟踪用于日志
     * 
     * @param Throwable $e
     * @return array
     */
    protected function formatStackTrace(Throwable $e): array
    {
        $trace = $e->getTrace();
        $formattedTrace = [];
        
        // 只取前5个堆栈信息
        $limit = min(5, count($trace));
        
        for ($i = 0; $i < $limit; $i++) {
            $item = $trace[$i];
            $formattedTrace[] = [
                'file' => $item['file'] ?? 'unknown',
                'line' => $item['line'] ?? 0,
                'function' => ($item['class'] ?? '') . ($item['type'] ?? '') . ($item['function'] ?? ''),
            ];
        }
        
        return $formattedTrace;
    }
} 