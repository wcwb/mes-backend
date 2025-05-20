<?php

namespace App\Observers;

use App\Models\TeamInvitation;
use Illuminate\Support\Facades\Log;

class TeamInvitationObserver
{
    /**
     * 处理TeamInvitation模型的"删除"事件
     * 
     * @param  \App\Models\TeamInvitation  $invitation
     * @return void
     */
    public function deleted(TeamInvitation $invitation)
    {
        // 确保这是一个软删除操作
        if (!$invitation->isForceDeleting()) {
            // 记录日志
            Log::channel('team_management')->info('团队邀请已软删除', [
                'invitation_id' => $invitation->id,
                'team_id' => $invitation->team_id,
                'email' => $invitation->email,
                'user_id' => auth()->id() ?? null
            ]);
        }
    }
    
    /**
     * 处理TeamInvitation模型的"恢复"事件
     * 
     * @param  \App\Models\TeamInvitation  $invitation
     * @return void
     */
    public function restored(TeamInvitation $invitation)
    {
        // 记录日志
        Log::channel('team_management')->info('团队邀请已恢复', [
            'invitation_id' => $invitation->id,
            'team_id' => $invitation->team_id,
            'email' => $invitation->email,
            'user_id' => auth()->id() ?? null
        ]);
    }
    
    /**
     * 处理TeamInvitation模型的"强制删除"事件
     *
     * @param  \App\Models\TeamInvitation  $invitation
     * @return void
     */
    public function forceDeleted(TeamInvitation $invitation)
    {
        // 记录日志
        Log::channel('security')->warning('团队邀请已永久删除', [
            'invitation_id' => $invitation->id,
            'team_id' => $invitation->team_id,
            'email' => $invitation->email,
            'user_id' => auth()->id() ?? null
        ]);
    }
} 