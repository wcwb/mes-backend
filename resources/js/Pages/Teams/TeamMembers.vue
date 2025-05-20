<template>
  <div class="p-6 bg-white rounded-lg shadow-md">
    <h2 class="text-2xl font-bold text-gray-800 mb-6">{{ $t('团队成员管理') }}</h2>
    
    <!-- 添加成员表单 -->
    <div v-if="isOwner" class="mb-8 p-4 border border-gray-200 rounded-lg bg-gray-50">
      <h3 class="text-lg font-semibold mb-4">{{ $t('添加新成员') }}</h3>
      <form @submit.prevent="addMember" class="space-y-4">
        <div>
          <label for="email" class="block text-sm font-medium text-gray-700">{{ $t('邮箱') }}</label>
          <input
            id="email"
            v-model="form.email"
            type="email"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            required
          />
          <p v-if="errors.email" class="mt-1 text-sm text-red-600">{{ errors.email }}</p>
        </div>
        
        <div>
          <label for="role" class="block text-sm font-medium text-gray-700">{{ $t('角色') }}</label>
          <select
            id="role"
            v-model="form.role"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            required
          >
            <option v-for="role in availableRoles" :key="role.key" :value="role.key">
              {{ role.name }}
            </option>
          </select>
        </div>
        
        <div class="flex justify-end">
          <button
            type="submit"
            class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
            :disabled="processing"
          >
            <span v-if="processing" class="mr-2">
              <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
            </span>
            {{ $t('添加成员') }}
          </button>
        </div>
      </form>
    </div>
    
    <!-- 成员列表 -->
    <div>
      <h3 class="text-lg font-semibold mb-4">{{ $t('团队成员') }}</h3>
      
      <div v-if="loading" class="flex justify-center my-8">
        <svg class="animate-spin h-8 w-8 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
      </div>
      
      <div v-else-if="members.length === 0" class="text-center py-8 text-gray-500">
        {{ $t('该团队暂无成员') }}
      </div>
      
      <div v-else class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                {{ $t('用户') }}
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                {{ $t('角色') }}
              </th>
              <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                {{ $t('操作') }}
              </th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="member in members" :key="member.id">
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center">
                  <div class="text-sm font-medium text-gray-900">
                    {{ member.name }}
                  </div>
                </div>
                <div class="text-sm text-gray-500">
                  {{ member.email }}
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span v-if="isOwner && member.id !== teamOwner.id" class="relative">
                  <select
                    v-model="member.role"
                    @change="updateMemberRole(member)"
                    class="block w-full pl-3 pr-10 py-1 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md"
                  >
                    <option v-for="role in availableRoles" :key="role.key" :value="role.key">
                      {{ role.name }}
                    </option>
                  </select>
                </span>
                <span v-else class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                      :class="member.id === teamOwner.id ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'">
                  {{ getRoleName(member.role) }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                <button
                  v-if="isOwner && member.id !== teamOwner.id"
                  @click="confirmRemove(member)"
                  class="text-red-600 hover:text-red-900"
                >
                  {{ $t('移除') }}
                </button>
                <span v-else-if="member.id === teamOwner.id" class="text-gray-400">
                  {{ $t('团队拥有者') }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
    
    <!-- 确认对话框 -->
    <div v-if="showConfirmDialog" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg p-6 max-w-md w-full">
        <h3 class="text-lg font-bold mb-4">{{ $t('确认移除成员') }}</h3>
        <p class="mb-6">{{ $t('您确定要将') }} <strong>{{ memberToRemove?.name }}</strong> {{ $t('从团队中移除吗？') }}</p>
        <div class="flex justify-end space-x-3">
          <button
            @click="showConfirmDialog = false"
            class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
          >
            {{ $t('取消') }}
          </button>
          <button
            @click="removeMember"
            class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700"
          >
            {{ $t('确认移除') }}
          </button>
        </div>
      </div>
    </div>
    
    <!-- 消息提示 -->
    <div v-if="message" class="fixed top-4 right-4 max-w-md bg-white shadow-lg rounded-lg overflow-hidden z-50 transition-opacity duration-300"
         :class="messageType === 'error' ? 'border-l-4 border-red-500' : 'border-l-4 border-green-500'">
      <div class="p-4">
        <div class="flex items-start">
          <div v-if="messageType === 'error'" class="flex-shrink-0">
            <svg class="h-6 w-6 text-red-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div v-else class="flex-shrink-0">
            <svg class="h-6 w-6 text-green-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
          </div>
          <div class="ml-3 w-0 flex-1 pt-0.5">
            <p class="text-sm font-medium text-gray-900">{{ message }}</p>
          </div>
          <div class="ml-4 flex-shrink-0 flex">
            <button @click="message = ''" class="inline-flex text-gray-400 hover:text-gray-500">
              <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import axios from 'axios'
import { route } from 'ziggy-js'

// 国际化
const { t } = useI18n()

// 路由参数获取团队ID
const teamId = ref(useRoute().params.teamId)

// 状态管理
const members = ref([])
const teamOwner = ref({})
const availableRoles = ref([])
const userRole = ref('')
const loading = ref(true)
const processing = ref(false)
const showConfirmDialog = ref(false)
const memberToRemove = ref(null)
const message = ref('')
const messageType = ref('success')
const errors = ref({})

// 表单数据
const form = ref({
  email: '',
  role: ''
})

// 计算属性：判断当前用户是否为团队拥有者
const isOwner = computed(() => {
  return userRole.value === 'owner'
})

// 获取团队成员列表
const fetchMembers = async () => {
  loading.value = true
  try {
    const response = await axios.get(route('api.team-members.index', { teamId: teamId.value }))
    members.value = response.data.users
    teamOwner.value = response.data.team.owner
    availableRoles.value = response.data.availableRoles
    userRole.value = response.data.userRole
  } catch (error) {
    showErrorMessage(error.response?.data?.message || t('获取团队成员失败'))
  } finally {
    loading.value = false
  }
}

// 添加团队成员
const addMember = async () => {
  processing.value = true
  errors.value = {}
  
  try {
    const response = await axios.post(route('api.team-members.store', { teamId: teamId.value }), form.value)
    showSuccessMessage(response.data.message || t('成员添加成功'))
    form.value.email = ''
    fetchMembers()
  } catch (error) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors || {}
    }
    showErrorMessage(error.response?.data?.message || t('添加成员失败'))
  } finally {
    processing.value = false
  }
}

// 更新成员角色
const updateMemberRole = async (member) => {
  try {
    const response = await axios.put(route('api.team-members.update', { 
      teamId: teamId.value, 
      userId: member.id 
    }), {
      role: member.role
    })
    
    showSuccessMessage(response.data.message || t('角色更新成功'))
  } catch (error) {
    showErrorMessage(error.response?.data?.message || t('更新角色失败'))
    // 重新获取成员列表以恢复原始角色
    fetchMembers()
  }
}

// 确认移除成员
const confirmRemove = (member) => {
  memberToRemove.value = member
  showConfirmDialog.value = true
}

// 移除团队成员
const removeMember = async () => {
  if (!memberToRemove.value) return
  
  try {
    const response = await axios.delete(route('api.team-members.destroy', { 
      teamId: teamId.value, 
      userId: memberToRemove.value.id 
    }))
    
    showSuccessMessage(response.data.message || t('成员已移除'))
    fetchMembers()
  } catch (error) {
    showErrorMessage(error.response?.data?.message || t('移除成员失败'))
  } finally {
    showConfirmDialog.value = false
    memberToRemove.value = null
  }
}

// 获取角色名称
const getRoleName = (roleKey) => {
  const role = availableRoles.value.find(r => r.key === roleKey)
  return role ? role.name : roleKey
}

// 显示成功消息
const showSuccessMessage = (msg) => {
  message.value = msg
  messageType.value = 'success'
  setTimeout(() => {
    message.value = ''
  }, 5000)
}

// 显示错误消息
const showErrorMessage = (msg) => {
  message.value = msg
  messageType.value = 'error'
  setTimeout(() => {
    message.value = ''
  }, 5000)
}

// 组件挂载时获取成员列表
onMounted(() => {
  fetchMembers()
})
</script> 