<?php

namespace App\Helpers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;

class SoftDeleteHelper
{
    /**
     * 软删除模型实例
     *
     * @param Model $model
     * @return bool
     */
    public static function softDelete(Model $model): bool
    {
        if (!in_array(SoftDeletes::class, class_uses_recursive($model))) {
            Log::channel('api_errors')->error('尝试软删除一个不支持软删除的模型', [
                'model' => get_class($model),
                'id' => $model->id,
            ]);
            return false;
        }
        
        try {
            $modelName = class_basename($model);
            $result = $model->delete();
            
            Log::channel('team_management')->info("成功软删除 {$modelName}", [
                'model' => get_class($model),
                'id' => $model->id,
                'user_id' => auth()->id() ?? null,
            ]);
            
            return $result;
        } catch (\Exception $e) {
            Log::channel('api_errors')->error('软删除操作失败', [
                'model' => get_class($model),
                'id' => $model->id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id() ?? null,
            ]);
            
            return false;
        }
    }
    
    /**
     * 恢复软删除的模型实例
     *
     * @param Model $model
     * @return bool
     */
    public static function restore(Model $model): bool
    {
        if (!in_array(SoftDeletes::class, class_uses_recursive($model)) || !method_exists($model, 'restore')) {
            Log::channel('api_errors')->error('尝试恢复一个不支持软删除的模型', [
                'model' => get_class($model),
                'id' => $model->id,
            ]);
            return false;
        }
        
        try {
            $modelName = class_basename($model);
            $result = $model->restore();
            
            Log::channel('team_management')->info("成功恢复 {$modelName}", [
                'model' => get_class($model),
                'id' => $model->id,
                'user_id' => auth()->id() ?? null,
            ]);
            
            return $result;
        } catch (\Exception $e) {
            Log::channel('api_errors')->error('恢复操作失败', [
                'model' => get_class($model),
                'id' => $model->id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id() ?? null,
            ]);
            
            return false;
        }
    }
    
    /**
     * 强制删除模型实例
     *
     * @param Model $model
     * @return bool
     */
    public static function forceDelete(Model $model): bool
    {
        if (!in_array(SoftDeletes::class, class_uses_recursive($model)) || !method_exists($model, 'forceDelete')) {
            Log::channel('api_errors')->error('尝试强制删除一个不支持软删除的模型', [
                'model' => get_class($model),
                'id' => $model->id,
            ]);
            return false;
        }
        
        try {
            $modelName = class_basename($model);
            $result = $model->forceDelete();
            
            Log::channel('security')->warning("永久删除 {$modelName}", [
                'model' => get_class($model),
                'id' => $model->id,
                'user_id' => auth()->id() ?? null,
            ]);
            
            return $result;
        } catch (\Exception $e) {
            Log::channel('api_errors')->error('强制删除操作失败', [
                'model' => get_class($model),
                'id' => $model->id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id() ?? null,
            ]);
            
            return false;
        }
    }
} 